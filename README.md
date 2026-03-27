# PsySpace : Plateforme de Gestion Clinique & Architecture Cloud

![CI/CD Deployment](https://github.com/hamadiaouina/Psyspace/actions/workflows/php-lint.yml/badge.svg)

## 1. Présentation du Projet
PsySpace est une solution logicielle dédiée à la gestion des dossiers patients pour les praticiens en psychologie. Ce Projet de Fin d'Études (PFE) met l'accent sur la haute disponibilité et l'automatisation du cycle de vie logiciel (DevOps) via une infrastructure Cloud.

## 2. Architecture & Stack Technique
L'écosystème repose sur une architecture découplée, optimisée pour le déploiement continu :

* **Environnement de Production :** Microsoft Azure App Service (Plan Linux / PHP 8.2).
* **Base de Données :** MySQL managé sur Azure.
* **Sécurité des Entrées :** Intégration de **Cloudflare Turnstile** (Captcha invisible) pour la protection contre les attaques par force brute.
* **Gestion des Secrets :** Isolation stricte des clés API et identifiants via les *Application Settings* d'Azure (Zéro secret dans le code source).

## 3. Pipeline CI/CD (GitHub Actions)
Le projet implémente une chaîne de déploiement automatisée localisée dans le répertoire `.github/workflows/` :

1. **Analyse Statique (Linting) :** Validation systématique de la syntaxe PHP via `php -l` à chaque Push.
2. **Déploiement Continu (CD) :** Synchronisation automatisée vers Azure Web Apps après validation des tests.
3. **Gestion d'Environnement :** Injection dynamique des configurations au runtime via les variables d'environnement système, éliminant le besoin de fichiers `.env` en production.

## 4. Installation et Développement Local

### Prérequis
* PHP 8.2+
* Serveur MySQL local (ou Docker Desktop)
* Clé API Cloudflare Turnstile

### Procédure de mise en route
1. **Clonage du dépôt**
   ```bash
   git clone [https://github.com/hamadiaouina/Psyspace.git](https://github.com/hamadiaouina/Psyspace.git)
   cd Psyspace
