# Scénario de Test — Module Hébergement & Réservation
**Projet :** Travelia — Module Hébergement  
**Auteur :** Oussema Fazzen  
**Date :** 07/05/2026

---

## Scénario Global : "Réservation complète d'un hébergement par un client"

Le scénario couvre le flux complet : création d'un hébergement par l'admin → réservation par le client → validation des contraintes métier.

---

## PARTIE 1 — Gestion des Hébergements (Admin)

### Cas de test 1 — Créer un hébergement valide

| Champ | Valeur de test |
|-------|---------------|
| Nom | Villa Jasmine |
| Type | Villa |
| Adresse | 12 Avenue Habib Bourguiba |
| Ville | Tunis |
| Pays | Tunisia |
| Capacité | 4 |
| Tarif par nuit | 150.00 |
| Équipements | WiFi, Piscine, Climatisation, Parking |

**Résultat attendu ✅ :** L'hébergement apparaît dans la liste avec toutes les données correctes.

---

### Cas de test 2 — Créer un hébergement avec données invalides

| Champ | Valeur de test | Erreur attendue |
|-------|---------------|-----------------|
| Nom | `AB` (trop court) | "Le nom doit contenir au moins 3 caractères" |
| Capacité | `-5` | "La capacité doit être un nombre positif" |
| Tarif par nuit | `-20` | "Le tarif doit être un nombre positif" |
| Nom | *(vide)* | "Le nom est obligatoire" |

**Résultat attendu ✅ :** Le formulaire affiche les messages d'erreur correspondants. Aucune ligne insérée en base de données.

---

### Cas de test 3 — Recherche et tri des hébergements

| Action | Valeur | Résultat attendu |
|--------|--------|-----------------|
| Recherche par nom | `Jasmine` | Affiche uniquement "Villa Jasmine" |
| Recherche sans résultat | `XYZABC` | Liste vide, pas d'erreur |
| Tri par ville | Clic sur "Ville" | Hébergements triés A→Z par ville |
| Tri par pays | Clic sur "Pays" | Hébergements triés A→Z par pays |

**Résultat attendu ✅ :** Le filtre et le tri fonctionnent sans rechargement de page.

---

## PARTIE 2 — Réservation d'Hébergement (Client)

### Cas de test 4 — Réservation valide dans les limites

| Champ | Valeur de test |
|-------|---------------|
| Hébergement | Villa Jasmine (capacité 4) |
| Date de début | 2026-06-01 |
| Date de fin | 2026-06-05 |
| Nombre de personnes | 3 |
| Client ID | 1 |

**Résultat attendu ✅ :** Réservation créée avec statut "en attente". Redirection vers le paiement.

---

### Cas de test 5 — Dépassement de capacité (règle métier clé)

| Champ | Valeur de test |
|-------|---------------|
| Hébergement | Villa Jasmine (capacité **4**) |
| Nombre de personnes | **5** (dépasse la capacité) |

**Résultat attendu ✅ :** Message d'erreur : *"Le nombre de personnes dépasse la capacité de l'hébergement (4)"*. Aucune réservation créée.

> **Note pour la validation :** Ce test est couvert par `testValidateCapacityDepasseeDeclencheViolation()` dans `ReservationhebergementTest.php`.

---

### Cas de test 6 — Date de début dans le passé

| Champ | Valeur de test |
|-------|---------------|
| Date de début | 2024-01-01 (passé) |
| Date de fin | 2026-06-05 |

**Résultat attendu ✅ :** Message d'erreur : *"La date de début ne peut pas être dans le passé"*.

---

### Cas de test 7 — Date de fin avant date de début

| Champ | Valeur de test |
|-------|---------------|
| Date de début | 2026-06-10 |
| Date de fin | 2026-06-05 (avant le début) |

**Résultat attendu ✅ :** Message d'erreur : *"La date de fin doit être après la date de début"*.

---

### Cas de test 8 — Exactement à la capacité max (cas limite)

| Champ | Valeur de test |
|-------|---------------|
| Hébergement | Villa Jasmine (capacité **4**) |
| Nombre de personnes | **4** (exactement la capacité) |

**Résultat attendu ✅ :** Réservation créée sans erreur. La limite est inclusive.

> **Note :** Ce test est couvert par `testValidateCapacityExactementEgalAucuneViolation()`.

---

## PARTIE 3 — Lien avec les Tests Unitaires

| Cas de test | Test PHPUnit correspondant |
|-------------|---------------------------|
| Cas 5 (dépassement capacité) | `ReservationhebergementTest::testValidateCapacityDepasseeDeclencheViolation` |
| Cas 8 (exactement à la limite) | `ReservationhebergementTest::testValidateCapacityExactementEgalAucuneViolation` |
| Cas 4 (dans les limites) | `ReservationhebergementTest::testValidateCapacityDansLimiteAucuneViolation` |
| Cas 6 (date passée) | `ReservationhebergementTest::testDateFinEstApresDateDebut` |
| Cas 1 (création valide) | `HebergementTest::testSetAndGetNom`, `testSetAndGetCapacite`, `testSetAndGetTarifParNuit` |

---

## Commandes pour lancer les tests

```bash
# Lancer tous les tests unitaires
php bin/phpunit --testdox

# Lancer uniquement les tests Hébergement
php bin/phpunit tests/Entity/HebergementTest.php --testdox

# Lancer uniquement les tests Réservation
php bin/phpunit tests/Entity/ReservationhebergementTest.php --testdox

# Lancer l'analyse statique PHPStan
vendor/bin/phpstan analyse --memory-limit=256M
```
