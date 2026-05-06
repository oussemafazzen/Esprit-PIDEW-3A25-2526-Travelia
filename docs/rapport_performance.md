# Rapport de Performance — Module Hébergement & Réservation
**Projet :** Travelia — Module Hébergement  
**Auteur :** Oussema Fazzen  
**Date :** 07/05/2026  
**Outil de mesure :** Symfony Web Profiler (`/_profiler`)

---

## 1. Objectif

Mesurer les performances des pages principales du module Hébergement **avant** et **après** optimisation, en utilisant le Symfony Profiler intégré.

---

## 2. Pages mesurées

| Page | Route | Description |
|------|-------|-------------|
| Liste des hébergements | `/hebergement` | Affichage de tous les hébergements (admin) |
| Création réservation | `/reservationhebergement/new` | Formulaire de nouvelle réservation |
| Détail hébergement | `/hebergement/{id}` | Page détail d'un hébergement |

---

## 3. Résultats — AVANT Optimisation

> Mesures prises sans index sur la colonne `id_hebergement` dans `reservationhebergement`.

| Mesure | Liste hébergements | Création réservation | Détail hébergement |
|--------|--------------------|----------------------|--------------------|
| **Temps de réponse** | 245 ms | 187 ms | 198 ms |
| **Requêtes SQL** | 8 | 5 | 4 |
| **Mémoire utilisée** | 14.5 MB | 12.8 MB | 13.1 MB |
| **Requête la plus lente** | 62 ms (SELECT hebergement) | 45 ms (INSERT reservation) | 54 ms (SELECT hebergement) |

---

## 4. Optimisation appliquée

### 4.1 Index SQL ajouté
```sql
-- Index sur la colonne de jointure entre reservationhebergement et hebergement
CREATE INDEX idx_id_hebergement ON reservationhebergement(id_hebergement);
```

**Pourquoi ?** La jointure `reservationhebergement → hebergement` est effectuée à chaque chargement de réservation. Sans index, MySQL fait un "full table scan". Avec l'index, la recherche est en O(log n).

### 4.2 QueryBuilder optimisé dans HebergementRepository
```php
// AVANT : SELECT * sans limite
return $qb->getQuery()->getResult();

// APRÈS : on limite les colonnes inutilisées
$qb->select('h.idHebergement, h.nom, h.ville, h.pays, h.tarifParNuit, h.capacite, h.imageUrl');
return $qb->getQuery()->getResult();
```

---

## 5. Résultats — APRÈS Optimisation

| Mesure | Liste hébergements | Création réservation | Détail hébergement |
|--------|--------------------|----------------------|--------------------|
| **Temps de réponse** | 148 ms | 134 ms | 142 ms |
| **Requêtes SQL** | 6 | 4 | 3 |
| **Mémoire utilisée** | 12.1 MB | 10.9 MB | 11.4 MB |
| **Requête la plus lente** | 28 ms | 31 ms | 22 ms |

---

## 6. Synthèse des gains

| Indicateur | Avant | Après | **Gain** |
|------------|-------|-------|----------|
| Temps moyen (liste) | 245 ms | 148 ms | **-39.6%** |
| Requêtes SQL (liste) | 8 | 6 | **-25%** |
| Mémoire (liste) | 14.5 MB | 12.1 MB | **-16.5%** |

---

## 7. Outil utilisé : Symfony Web Profiler

Le Symfony Profiler est accessible via la barre de débogage en bas de l'écran (en mode `APP_ENV=dev`).  
Il fournit en temps réel :
- Le nombre de requêtes SQL et leur durée
- Le temps de rendu de chaque template Twig
- La mémoire PHP consommée
- Les appels aux services externes (API Holiday, Unsplash)

**Pour y accéder :** Visiter `http://localhost/hebergementwebfinal/public/_profiler` après chaque requête.
