# StageConnect AI - Frontend

Une plateforme de stage intelligente propulsée par l'Intelligence Artificielle qui connecte les étudiants aux entreprises grâce à un algorithme de matching avancé.

## 🌟 Fonctionnalités

- **Matching IA Intelligent**: Algorithme avancé pour trouver le meilleur stage selon le profil de l'étudiant
- **Profil Complet**: Gestion détaillée du profil étudiant avec compétences, expériences et CV
- **Gestion des Offres**: Publication et gestion des offres de stage pour les entreprises
- **CV Interactif**: Upload et gestion des CV directement sur la plateforme
- **Notifications en Temps Réel**: Alertes instantanées pour les nouvelles offres et candidatures
- **Analytics Détaillés**: Statistiques et insights pour améliorer le profil

## 🚀 Technologies

- **Framework**: Angular 17.3.0
- **Language**: TypeScript 5.4.2
- **UI Components**: Angular CDK 17.3.10
- **Charts**: Chart.js 4.6.1 avec ng2-charts 6.0.1
- **Build Tool**: Angular CLI 17.3.17

## 📋 Prérequis

- Node.js 18.x ou supérieur
- npm 9.x ou supérieur
- Angular CLI 17.3.17

## 🛠️ Installation

```bash
# Installer les dépendances
npm install

# Démarrer le serveur de développement
ng serve
```

L'application sera accessible sur `http://localhost:4200/`. Le rechargement automatique est activé lors des modifications.

## 📦 Structure du Projet

```
src/
├── app/
│   ├── components/          # Composants réutilisables
│   │   ├── auth/           # Composants d'authentification
│   │   └── welcome/        # Page d'accueil
│   ├── dashboard/          # Tableau de bord principal
│   ├── messages/           # Système de messagerie
│   ├── profile/            # Gestion du profil
│   ├── services/           # Services API
│   └── shared/             # Composants partagés
├── assets/                 # Assets statiques
└── styles.css              # Styles globaux
```

## 🔧 Scripts Disponibles

```bash
# Serveur de développement
ng serve

# Build pour la production
ng build

# Build avec watch
ng build --watch --configuration development

# Exécuter les tests unitaires
ng test

# Exécuter les tests end-to-end
ng e2e
```

## 🎨 Design System

Le projet utilise un système de design professionnel avec :

- **Palette de couleurs**: Bleu corporatif (#1e40af) comme couleur primaire
- **Typographie**: Inter pour une lisibilité optimale
- **Composants**: Bibliothèque de composants réutilisables
- **Responsive Design**: Adapté pour mobile, tablette et desktop

## 📝 Conventions de Code

- Utiliser TypeScript strict
- Suivre les conventions Angular
- Composants avec PascalCase
- Services avec suffixe `.service.ts`
- Interfaces avec préfixe `I`

## 🔐 Sécurité

- Authentification JWT
- Protection CSRF
- Validation des formulaires
- Sanitization des entrées

## 🌐 API Integration

Le frontend communique avec le backend via REST API. Configurez l'URL de l'API dans les fichiers d'environnement :

```typescript
// src/environments/environment.ts
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api'
};
```

## 📱 Responsive Design

L'application est entièrement responsive avec des breakpoints :
- Mobile: < 640px
- Tablet: 640px - 1024px
- Desktop: > 1024px

## 🧪 Tests

Les tests sont écrits avec Jasmine et Karma. Pour exécuter tous les tests :

```bash
ng test
```

## 🚀 Build de Production

```bash
# Build optimisé pour la production
ng build --configuration production

# Les fichiers seront générés dans dist/
```

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est sous licence propriétaire.

## 👥 Équipe

- Développement Frontend
- Design UI/UX
- Backend Integration

## 📞 Support

Pour toute question ou problème, contactez l'équipe de développement.

---

**StageConnect AI** - La plateforme de stage intelligente
