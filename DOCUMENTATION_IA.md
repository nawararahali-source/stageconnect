# Documentation - Analyse Intelligente du Profil Étudiant (US20)

## Vue d'ensemble

Cette fonctionnalité permet aux étudiants d'obtenir une analyse personnalisée de leur profil grâce à l'intelligence artificielle Google Gemini. L'analyse inclut un résumé du profil, les points forts, les compétences maîtrisées, les compétences à améliorer et des recommandations concrètes.

## Architecture de la fonctionnalité

### Backend Laravel

#### 1. Service IA (`app/Services/AIService.php`)
- **Responsabilité** : Communication avec l'API Google Gemini
- **Fonctionnalités** :
  - Construction du prompt avec les données du profil étudiant
  - Appel à l'API Gemini via Guzzle HTTP
  - Parsing de la réponse JSON
  - Gestion des erreurs et logging
- **Configuration** : Utilise les variables d'environnement pour la clé API et le modèle

#### 2. Controller (`app/Http/Controllers/ProfileAnalysisController.php`)
- **Responsabilité** : Endpoint API pour l'analyse de profil
- **Fonctionnalités** :
  - Vérification que l'utilisateur est un étudiant
  - Récupération des données du profil, compétences, expériences, projets, langues
  - Extraction du contenu du CV (placeholder pour future implémentation)
  - Appel au service IA
  - Retour de l'analyse au frontend
- **Sécurité** : Protégé par middleware `auth:sanctum`, vérifie que l'utilisateur analyse son propre profil

#### 3. Route API (`routes/api.php`)
- **Endpoint** : `POST /api/profile/analyze`
- **Protection** : `auth:sanctum`
- **Accès** : Réservé aux étudiants (vérifié dans le controller)

### Frontend Angular

#### 1. Service (`src/app/services/profile-analysis.service.ts`)
- **Responsabilité** : Communication avec l'API backend
- **Fonctionnalités** :
  - Méthode `analyzeProfile()` pour déclencher l'analyse
  - Gestion du token d'authentification
  - Typage TypeScript pour la réponse de l'analyse

#### 2. Composant (`src/app/profile/profile.component.ts`)
- **Responsabilité** : Interface utilisateur pour l'analyse
- **Fonctionnalités** :
  - Bouton "Analyser mon profil avec l'IA"
  - Indicateur de chargement pendant l'analyse
  - Affichage des résultats dans une modale
  - Gestion des erreurs
  - Vérification que l'utilisateur est un étudiant et a un profil

#### 3. Template (`src/app/profile/profile.component.html`)
- **Éléments ajoutés** :
  - Bouton d'analyse dans la section "dashboard-overview"
  - Modale pour afficher les résultats de l'analyse
  - Sections pour : résumé, points forts, compétences maîtrisées, compétences à améliorer, recommandations

#### 4. Styles (`src/app/profile/profile.component.css`)
- **Styles ajoutés** :
  - Bouton avec dégradé violet pour l'analyse
  - Effets hover et disabled
  - Texte d'aide sous le bouton

## Étapes de configuration

### 1. Configuration de la clé API Google Gemini

#### Étape 1 : Obtenir une clé API Gemini
1. Créer un compte sur [Google AI Studio](https://aistudio.google.com/)
2. Aller dans la section API Keys
3. Créer une nouvelle clé API
4. Copier la clé

#### Étape 2 : Configurer le fichier `.env`
Ouvrir le fichier `backend/.env` et ajouter les lignes suivantes :

```env
GEMINI_API_KEY=votre_clé_api_ici
GEMINI_MODEL=gemini-3.6-flash
```

**Important** :
- Remplacez `votre_clé_api_ici` par votre vraie clé API
- Ne commitez jamais le fichier `.env` dans Git (déjà dans `.gitignore`)
- Le fichier `.env.example` a été mis à jour avec les variables nécessaires

#### Étape 3 : Redémarrer le serveur Laravel
Après avoir modifié le `.env`, redémarrer le serveur Laravel pour charger les nouvelles variables :

```bash
cd backend
php artisan serve
```

### 2. Alternative : Utiliser un autre modèle Gemini

Si vous souhaitez utiliser un autre modèle Gemini, modifiez la variable d'environnement :

```env
GEMINI_MODEL=gemini-3.7-flash
```

**Modèles disponibles sur Free Tier** :
- `gemini-3.6-flash` - Rapide et économique (recommandé, modèle par défaut)
- `gemini-3.7-flash` - Plus puissant mais peut être surchargé
- `gemini-3.5-flash` - Modèle précédent
- `gemini-3.5-flash-lite` - Plus léger

## Flux de données

1. **Étudiant clique sur "Analyser mon profil avec l'IA"**
   - Frontend vérifie que l'utilisateur est un étudiant et a un profil
   - Appel à `profileAnalysisService.analyzeProfile()`

2. **Backend reçoit la requête**
   - Middleware `auth:sanctum` vérifie l'authentification
   - Controller vérifie que l'utilisateur est un étudiant
   - Controller récupère toutes les données du profil

3. **Appel au service IA**
   - Service construit le prompt avec toutes les données
   - Service appelle l'API Gemini avec la clé API du `.env`
   - Service parse la réponse JSON

4. **Retour au frontend**
   - Backend renvoie l'analyse structurée
   - Frontend affiche la modale avec les résultats

## Données envoyées à l'IA

Le prompt inclut :
- Informations de base (nom, formation, spécialité, niveau, bio)
- Compétences avec niveaux
- Expériences professionnelles
- Projets réalisés
- Langues parlées
- Contenu du CV (si extraction disponible)

## Structure de la réponse IA

```json
{
  "resume": "Résumé du profil en 2-3 phrases",
  "points_forts": ["Point fort 1", "Point fort 2", "Point fort 3"],
  "competences_maitrisees": ["Compétence 1", "Compétence 2"],
  "competences_a_ameliorer": ["Compétence 1", "Compétence 2"],
  "recommandations": ["Recommandation 1", "Recommandation 2", "Recommandation 3"]
}
```

## Sécurité

- **Clé API** : Jamais exposée dans le frontend, uniquement dans le backend
- **Authentification** : Endpoint protégé par Laravel Sanctum
- **Autorisation** : Seul l'étudiant connecté peut analyser son propre profil
- **Validation** : Vérification que l'utilisateur a un profil avant l'analyse
- **Logging** : Erreurs loggées pour debugging

## Limitations actuelles

1. **Extraction de texte PDF** : Non implémentée dans cette version
   - Le CV est stocké mais son contenu n'est pas extrait
   - Pour une implémentation future, installer un package comme `smalot/pdfparser`

2. **Modèle IA** : Utilise gemini-3.8-flash par défaut
   - Peut être changé dans le `.env` pour gemini-3.8-pro ou autre modèle

3. **Limites Free Tier** : Google Gemini a des limites de requêtes sur le Free Tier
   - Surveiller l'utilisation via le dashboard Google AI Studio
   - Implémenter une limite de requêtes par utilisateur si nécessaire

## Tests recommandés

### Test 1 : Profil étudiant complet
1. Créer un compte étudiant
2. Remplir le profil avec :
   - Formation, spécialité, niveau
   - Bio détaillée
   - Plusieurs compétences
   - Expériences professionnelles
   - Projets
   - Langues
3. Cliquer sur "Analyser mon profil avec l'IA"
4. Vérifier que l'analyse est générée et affichée correctement

### Test 2 : Profil étudiant minimal
1. Créer un autre compte étudiant
2. Remplir uniquement les champs obligatoires
3. Cliquer sur "Analyser mon profil avec l'IA"
4. Vérifier que l'analyse fonctionne même avec peu de données

### Test 3 : Utilisateur entreprise
1. Se connecter avec un compte entreprise
2. Vérifier que le bouton d'analyse n'est pas visible
3. Tenter d'accéder directement à l'endpoint API (doit retourner 403)

### Test 4 : Sans profil
1. Créer un compte étudiant sans remplir le profil
2. Cliquer sur "Analyser mon profil avec l'IA"
3. Vérifier le message d'erreur approprié

### Test 5 : Clé API non configurée
1. Arrêter le serveur Laravel
2. Ouvrir `backend/.env`
3. Commenter ou supprimer la ligne `GEMINI_API_KEY`
4. Redémarrer le serveur Laravel
5. Se connecter avec un compte étudiant
6. Cliquer sur "Analyser mon profil avec l'IA"
7. Vérifier le message d'erreur : "Clé API Gemini non configurée. Veuillez configurer GEMINI_API_KEY dans le fichier .env"
8. Restaurer la clé API dans le `.env`
9. Redémarrer le serveur

## Fichiers créés/modifiés

### Backend Laravel
- **Créés** :
  - `app/Services/AIService.php` - Service de communication avec l'API IA
  - `app/Http/Controllers/ProfileAnalysisController.php` - Controller pour l'endpoint d'analyse

- **Modifiés** :
  - `routes/api.php` - Ajout de la route `POST /api/profile/analyze`
  - `.env.example` - Ajout des variables de configuration OpenAI

### Frontend Angular
- **Créés** :
  - `src/app/services/profile-analysis.service.ts` - Service pour appelerl'API d'analyse

- **Modifiés** :
  - `src/app/profile/profile.component.ts` - Ajout de la logique d'analyse
  - `src/app/profile/profile.component.html` - Ajout du bouton et de la modale
  - `src/app/profile/profile.component.css` - Styles pour le bouton d'analyse

## Présentation à l'encadrante

### Démo suggérée
1. Montrer le bouton "Analyser mon profil avec l'IA" dans l'espace étudiant
2. Cliquer sur le bouton et montrer l'indicateur de chargement
3. Afficher les résultats de l'analyse dans la modale
4. Expliquer les différentes sections de l'analyse
5. Montrer que la fonctionnalité est réservée aux étudiants

### Points à mettre en avant
- **Intégration réelle avec une API IA** (pas de données fictives)
- **Sécurité** : clé API uniquement dans le backend
- **Réutilisation** : utilise les modèles et services existants
- **Extensibilité** : facile à adapter pour d'autres fonctionnalités IA
- **UX** : interface claire avec feedback utilisateur

### Améliorations futures possibles
- Extraction du contenu des CV PDF
- Historique des analyses
- Comparaison d'analyses dans le temps
- Recommandations d'offres basées sur l'analyse
- Chatbot pour poser des questions sur son profil
