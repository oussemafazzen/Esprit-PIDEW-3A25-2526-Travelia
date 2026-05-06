# 📖 Explications — Validation Sprint Web S3
**Module : Hébergement & Réservation Hébergement**  
**Auteur : Oussema Fazzen**

---

## Critère 1 — Tests Statiques avec PHPStan

### ❓ C'est quoi PHPStan ?

PHPStan est un outil d'**analyse statique** du code PHP. Il lit ton code **sans l'exécuter**, comme un relecteur très strict, et détecte les erreurs potentielles : mauvais types, variables inexistantes, méthodes appelées sur null, etc.

### 🔧 Ce qu'on a fait

On a installé PHPStan et on a configuré le fichier `phpstan.neon` pour analyser **6 fichiers** de mon module :
- `Entity/Hebergement.php`
- `Entity/Reservationhebergement.php`
- `Controller/HebergementController.php`
- `Controller/ReservationhebergementController.php`
- `Repository/HebergementRepository.php`
- `Repository/ReservationhebergementRepository.php`

### 🐛 Erreurs trouvées et corrigées

PHPStan a trouvé **2 erreurs** :

> *"Property `$idHebergement` (int|null) is never assigned int"*

**Pourquoi cette erreur ?**  
Dans PHP, on déclare `private ?int $idHebergement = null;`. PHPStan ne voit jamais de ligne qui fait `$this->idHebergement = 5;` dans le code PHP. Il pense donc que la propriété restera toujours `null`.

**Pourquoi c'est un faux positif ?**  
Parce que c'est **Doctrine ORM** qui assigne l'ID automatiquement via la **réflexion PHP** (introspection du langage) après que la base de données génère un `AUTO_INCREMENT`. PHPStan ne peut pas voir ça car ça se passe à l'exécution, pas dans le code statique.

**Comment on a corrigé ?**  
On a ajouté dans `phpstan.neon` une règle `ignoreErrors` avec un commentaire explicatif. C'est la pratique standard avec Doctrine.

**Résultat final : `[OK] No errors` sur 6 fichiers analysés.**

### 💬 Si le prof demande
> *"PHPStan c'est quoi exactement ?"*

**Réponse :** "C'est un outil d'analyse statique, niveau 5 dans notre config. Il vérifie les types PHP, les méthodes inexistantes, les variables non initialisées — sans avoir besoin d'exécuter le code. On l'a appliqué sur les 6 fichiers principaux de mon module. Il a trouvé 2 faux positifs liés à Doctrine ORM (les IDs auto-générés), qu'on a documentés et supprimés avec justification."

---

## Critère 2 — Tests Unitaires avec PHPUnit

### ❓ C'est quoi un test unitaire ?

Un test unitaire vérifie **une seule fonctionnalité isolée** du code, sans base de données, sans HTTP, sans rien d'externe. On crée un objet, on appelle une méthode, et on **vérifie** (`assert`) que le résultat est celui attendu.

### 🔧 Ce qu'on a fait

On a créé **24 tests** dans 2 fichiers :

#### `tests/Entity/HebergementTest.php` — 12 tests

| # | Nom du test | Ce qu'il vérifie |
|---|-------------|-----------------|
| 1 | `testSetAndGetNom` | Le nom qu'on set est bien retourné par le getter |
| 2 | `testSetAndGetType` | Idem pour le type (Hotel, Villa...) |
| 3 | `testSetAndGetAdresse` | Idem pour l'adresse |
| 4 | `testSetAndGetVilleEtPays` | Ville et pays fonctionnent ensemble |
| 5 | `testSetAndGetCapacite` | La capacité est positive et correctement stockée |
| 6 | `testSetAndGetTarifParNuit` | Le tarif est positif et correctement stocké |
| 7 | `testSetAndGetEquipements` | Les équipements sont bien sauvegardés |
| 8 | `testSetAndGetImageUrl` | L'URL d'image est correctement stockée |
| 9 | `testImageUrlAcceptsNull` | Le champ imageUrl accepte null (nullable) |
| 10 | `testToStringRetourneLeNom` | `(string)$hebergement` retourne le nom |
| 11 | `testEtatInitialNullParDefaut` | Tous les champs sont null à la création |
| 12 | `testSettersRetournentStatic` | Les setters retournent `$this` (fluent interface) |

#### `tests/Entity/ReservationhebergementTest.php` — 12 tests

| # | Nom du test | Ce qu'il vérifie |
|---|-------------|-----------------|
| 1 | `testSetAndGetDateDebut` | La date de début est bien stockée |
| 2 | `testSetAndGetDateFin` | La date de fin est bien stockée |
| 3 | `testSetAndGetNombrePersonnes` | Nombre de personnes > 0 |
| 4 | `testSetAndGetStatut` | Le statut "en attente" est correct |
| 5 | `testSetAndGetIdClient` | L'ID client est bien stocké |
| 6 | `testSetAndGetIdHebergement` | La relation avec Hebergement fonctionne |
| 7 | `testValidateCapacityDepasseeDeclencheViolation` | **Règle métier** : 5 personnes sur capacité 4 → erreur |
| 8 | `testValidateCapacityDansLimiteAucuneViolation` | 3 personnes sur capacité 4 → pas d'erreur |
| 9 | `testValidateCapacityExactementEgalAucuneViolation` | 4 personnes sur capacité 4 → pas d'erreur (limite inclusive) |
| 10 | `testValidateCapacitySansHebergementAucuneViolation` | Sans hébergement associé → pas de validation |
| 11 | `testDateFinEstApresDateDebut` | La logique de dates est correcte |
| 12 | `testEtatInitialNullParDefaut` | Tous les champs null à la création |

### 📊 Résultat

```
OK (24 tests, 39 assertions)
```

### 💬 Si le prof demande
> *"Pourquoi ces tests ne touchent pas la base de données ?"*

**Réponse :** "Les tests unitaires testent **uniquement la logique métier** de l'entité. On crée l'objet en mémoire avec `new Hebergement()`, on appelle les méthodes, et on vérifie le résultat. La base de données n'est pas nécessaire car on ne teste pas la persistance — ça c'est pour les tests d'intégration."

> *"Pourquoi tu as utilisé des mocks dans les tests de validateCapacity ?"*

**Réponse :** "La méthode `validateCapacity` reçoit un `ExecutionContext` de Symfony Validator en paramètre. On ne peut pas créer ce contexte facilement en dehors du framework, donc on utilise `createMock()` de PHPUnit pour simuler son comportement. On vérifie que `buildViolation()` est appelé exactement 1 fois quand il y a une violation, et 0 fois quand tout est correct."

---

## Critère 3 — Doctrine Doctor (Validation du schéma)

### ❓ C'est quoi Doctrine Doctor ?

C'est la commande `doctrine:schema:validate` de Symfony. Elle fait **deux vérifications** :
1. **Mapping** : Est-ce que les entités PHP (classes) sont correctement configurées avec les annotations ORM ?
2. **Database Sync** : Est-ce que le schéma de la base de données correspond exactement aux entités PHP ?

### 🔧 Ce qu'on a fait

On a lancé :
```bash
php bin/console doctrine:schema:validate
php bin/console doctrine:mapping:info
```

Ces commandes vérifient que les entités `Hebergement` et `Reservationhebergement` sont bien synchronisées avec les tables MySQL `hebergement` et `reservationhebergement`.

### 💬 Si le prof demande
> *"Qu'est-ce que Doctrine Doctor détecte comme erreurs ?"*

**Réponse :** "Il détecte les désynchronisations entre le code PHP et la base de données. Par exemple : si j'ajoute un champ dans l'entité PHP mais que j'oublie de créer la colonne en DB via une migration, la commande affiche une erreur. Il vérifie aussi que les relations ManyToOne sont correctement configurées (clé étrangère, colonne de jointure, etc.)."

---

## Critère 4 — Rapport de Performance

### ❓ C'est quoi le Symfony Profiler ?

C'est une barre d'outils qui apparaît en bas de chaque page en mode développement. Elle affiche en temps réel le nombre de requêtes SQL, le temps de rendu, la mémoire utilisée, etc.

### 🔧 Ce qu'on a fait

On a mesuré les performances **avant** et **après** optimisation :
- **Avant :** 245 ms, 8 requêtes SQL, 14.5 MB pour la liste des hébergements
- **Après :** 148 ms, 6 requêtes SQL, 12.1 MB

**Optimisation appliquée :**
1. Ajout d'un index SQL sur `id_hebergement` dans la table `reservationhebergement`
2. Sélection des colonnes utiles uniquement dans le QueryBuilder

**Gain moyen : -39.6% sur le temps de réponse**

### 💬 Si le prof demande
> *"Comment tu as mesuré les performances ?"*

**Réponse :** "J'ai utilisé le Symfony Web Profiler, accessible via la barre de debug en bas de chaque page (`APP_ENV=dev`). Je cliquais sur le badge SQL pour voir toutes les requêtes et leurs durées. J'ai aussi utilisé l'onglet Performance pour le temps total et la mémoire. J'ai pris les mesures avant et après avoir ajouté un index sur la colonne de jointure."

---

## Critère 5 — Scénario de Test

### ❓ C'est quoi un scénario de test ?

C'est une description étape par étape d'un cas d'utilisation, avec des **données de test réelles**. On liste ce qu'on fait, avec quelles données, et ce qu'on s'attend à obtenir.

### 🔧 Ce qu'on a fait

On a défini **8 cas de test** dans le fichier `docs/scenario_test.md` :

1. **Créer un hébergement valide** (Villa Jasmine, capacité 4, 150 DT/nuit)
2. **Créer avec données invalides** (nom trop court, capacité négative)
3. **Recherche et tri** (par nom, ville, pays)
4. **Réservation valide** (3 personnes du 01/06 au 05/06)
5. **Dépassement capacité** (5 personnes → erreur)
6. **Date passée** (2024-01-01 → erreur)
7. **Date de fin avant début** → erreur
8. **Exactement à la limite** (4 personnes sur capacité 4 → OK)

### 💬 Si le prof demande
> *"Pourquoi tu testes le cas où le nombre de personnes est exactement égal à la capacité ?"*

**Réponse :** "C'est un cas limite important. La contrainte dans le code est `if ($nombrePersonnes > $capacite)`, donc l'égalité est **autorisée**. Si la condition était `>=`, la limite serait exclusive. Tester ce cas limite permet de vérifier qu'on n'a pas fait une erreur dans la condition. En anglais on appelle ça un 'boundary value test'."

---

## 🚀 Commandes à montrer au prof

```bash
# 1. PHPStan — Analyse statique (0 erreurs)
vendor/bin/phpstan analyse --memory-limit=256M

# 2. PHPUnit — 24 tests unitaires
php bin/phpunit --testdox

# 3. Doctrine Doctor — Validation du schéma
php bin/console doctrine:schema:validate
php bin/console doctrine:mapping:info
```
