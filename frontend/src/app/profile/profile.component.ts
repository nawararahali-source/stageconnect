import { Component, HostListener, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { ProfileResponse, ProfileService } from '../services/profile.service';
import { AuthService } from '../services/auth.service';
import { SkillService } from '../services/skill.service';
import { CvService } from '../services/cv.service';
import { ProfileAnalysisService, ProfileAnalysis } from '../services/profile-analysis.service';
import { StudentExperience, StudentLanguage, StudentProject } from '../services/profile.service';
import { SidebarComponent } from '../shared/sidebar.component';
import { NotificationToastComponent } from '../shared/notification-toast.component';

interface ProfileSkill {
  id: number;
  name: string;
  level: 'débutant' | 'intermédiaire' | 'avancé';
}

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, SidebarComponent, NotificationToastComponent],
  templateUrl: './profile.component.html',
  styleUrl: './profile.component.css'
})
export class ProfileComponent implements OnInit, OnDestroy {
  user: any = null;
  profile: any = null;
  hasProfile = false;
  profileCompletionPercentage = 0;
  profileMissingItems: string[] = [];
  isLoading = false;
  isCheckingProfile = true;
  isEditMode = false;
  isUploadingPhoto = false;
  errorMessage = '';
  successMessage = '';
  photoPreview: string | null = null;

  // Skills data
  skills: ProfileSkill[] = [];
  isLoadingSkills = false;
  newSkill = {
    name: '',
    level: 'intermédiaire' as 'débutant' | 'intermédiaire' | 'avancé'
  };
  isAddingSkill = false;
  editingSkillId: number | null = null;
  isUpdatingSkillId: number | null = null;
  editSkillData = {
    name: '',
    level: 'intermédiaire' as 'débutant' | 'intermédiaire' | 'avancé'
  };

  // CV data
  cvs: any[] = [];
  isLoadingCv = false;
  isUploadingCv = false;

  // AI Analysis data
  analysis: ProfileAnalysis | null = null;
  isAnalyzing = false;
  showAnalysis = false;

  // Sidebar state
  isSidebarCollapsed = false;

  // Notification state
  showNotification = false;
  notificationType: 'success' | 'error' | 'warning' | 'info' = 'info';
  notificationTitle = '';
  notificationMessage = '';
  private fragmentSubscription?: Subscription;
  private pendingCompletionFragment: string | null = null;

  // Student profile data
  studentData = {
    formation: '',
    specialite: '',
    niveau: '',
    telephone: '',
    adresse: '',
    ville: '',
    bio: '',
    linkedin: '',
    github: '',
    experiences: [] as StudentExperience[],
    projects: [] as StudentProject[],
    languages: [] as StudentLanguage[]
  };

  // Company profile data
  companyData = {
    nom_entreprise: '',
    secteur_activite: '',
    adresse: '',
    description: '',
    ville: '',
    telephone: '',
    site_web: '',
    linkedin: ''
  };

  // Edit data (for editing existing profile)
  editStudentData = {
    formation: '',
    specialite: '',
    niveau: '',
    telephone: '',
    adresse: '',
    ville: '',
    bio: '',
    linkedin: '',
    github: '',
    experiences: [] as StudentExperience[],
    projects: [] as StudentProject[],
    languages: [] as StudentLanguage[]
  };

  editCompanyData = {
    nom_entreprise: '',
    secteur_activite: '',
    adresse: '',
    description: '',
    ville: '',
    telephone: '',
    site_web: '',
    linkedin: ''
  };

  constructor(
    private profileService: ProfileService,
    private authService: AuthService,
    private skillService: SkillService,
    private cvService: CvService,
    private profileAnalysisService: ProfileAnalysisService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.isSidebarCollapsed = window.innerWidth <= 1024;
    this.fragmentSubscription = this.route.fragment.subscribe((fragment) => {
      this.pendingCompletionFragment = fragment;
      if (!this.isCheckingProfile) {
        this.applyCompletionFragment(fragment);
      }
    });

    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['']);
      return;
    }

    // Use user data from localStorage instead of calling /api/me
    const user = this.authService.getUser();
    if (user) {
      this.user = user;
      this.checkProfile();
      if (this.user.role === 'etudiant') {
        this.loadSkills();
        this.loadCvs();
      }
    } else {
      // Fallback to API call if no user data in localStorage
      this.authService.me().subscribe({
        next: (response) => {
          this.user = response.user;
          this.authService.setUser(response.user);
          this.checkProfile();
          if (this.user.role === 'etudiant') {
            this.loadSkills();
            this.loadCvs();
          }
        },
        error: (error) => {
          console.error('Error fetching user:', error);
          this.router.navigate(['']);
        }
      });
    }
  }

  ngOnDestroy(): void {
    this.fragmentSubscription?.unsubscribe();
  }

  checkProfile(): void {
    this.profileService.getProfile().subscribe({
      next: (response) => {
        if (response.success && response.profile) {
          this.profile = response.profile;
          this.hasProfile = true;
          this.updateCompletion(response);
        }
        this.isCheckingProfile = false;
        this.applyCompletionFragment(this.pendingCompletionFragment);
      },
      error: (error) => {
        // Profile doesn't exist (404) - show creation form
        if (error.status === 404) {
          this.hasProfile = false;
          this.profileCompletionPercentage = error.error?.completion_percentage ?? 0;
          this.profileMissingItems = error.error?.missing_items ?? [];
        } else if (error.status === 401) {
          this.handleUnauthorized();
        } else if (error.status === 0) {
          this.errorMessage = 'Le serveur Laravel est inaccessible. Démarrez le backend sur le port 8000 puis réessayez.';
        } else {
          console.error('Error checking profile:', error);
          this.errorMessage = error.error?.message || 'Impossible de charger votre profil. Réessayez.';
        }
        this.isCheckingProfile = false;
        this.applyCompletionFragment(this.pendingCompletionFragment);
      }
    });
  }

  completionTarget(item: string): string {
    const normalizedItem = item.toLocaleLowerCase();
    if (!this.hasProfile) {
      if (normalizedItem.includes('formation')) return 'formation';
      if (normalizedItem.includes('téléphone')) return 'telephone';
      if (normalizedItem.includes('ville')) return 'ville';
      if (normalizedItem.includes('biographie')) return 'bio';
      if (normalizedItem.includes('linkedin') || normalizedItem.includes('github')) return 'linkedin';
      return 'profile-creation-form';
    }

    if (normalizedItem.includes('formation')) return 'edit-formation';
    if (normalizedItem.includes('téléphone')) return 'edit-telephone';
    if (normalizedItem.includes('ville')) return 'edit-ville';
    if (normalizedItem.includes('biographie')) return 'edit-bio';
    if (normalizedItem.includes('compétences')) return 'skills';
    if (normalizedItem.includes('expérience')) return 'profile-experiences';
    if (normalizedItem.includes('projet')) return 'profile-projects';
    if (normalizedItem.includes('langue')) return 'profile-languages';
    if (normalizedItem.includes('cv')) return 'cv';
    if (normalizedItem.includes('photo')) return 'profile-photo';
    if (normalizedItem.includes('linkedin') || normalizedItem.includes('github')) return 'edit-linkedin';
    return 'profile-creation-form';
  }

  navigateToCompletionItem(item: string): void {
    this.router.navigate(['/profile'], { fragment: this.completionTarget(item) });
  }

  private applyCompletionFragment(fragment: string | null): void {
    if (!fragment || this.isCheckingProfile) {
      return;
    }

    if (this.hasProfile
      && (fragment.startsWith('edit-') || fragment.startsWith('profile-experiences')
        || fragment.startsWith('profile-projects') || fragment.startsWith('profile-languages'))
      && !this.isEditMode) {
      this.enableEditMode();
    }

    const targetId = fragment;
    window.setTimeout(() => {
      document.getElementById(targetId)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }

  private updateCompletion(response: ProfileResponse): void {
    this.profileCompletionPercentage = response.completion_percentage
      ?? response.profile?.['completion_percentage']
      ?? 0;
    this.profileMissingItems = response.missing_items
      ?? response.profile?.['missing_items']
      ?? [];
  }

  private refreshCompletion(): void {
    this.profileService.getProfile().subscribe({
      next: (response) => {
        this.updateCompletion(response);
        if (this.profile) {
          this.profile.completion_percentage = this.profileCompletionPercentage;
          this.profile.missing_items = this.profileMissingItems;
        }
      },
      error: (error) => {
        if (error.status !== 404) {
          console.error('Impossible de rafraîchir la complétion du profil.', error);
        }
      }
    });
  }

  enableEditMode(): void {
    this.isEditMode = true;
    this.errorMessage = '';
    this.successMessage = '';

    // Pre-fill edit data with current profile values
    if (this.user.role === 'etudiant') {
      this.editStudentData = {
        formation: this.profile.formation || '',
        specialite: this.profile.specialite || '',
        niveau: this.profile.niveau || '',
        telephone: this.profile.telephone || '',
        adresse: this.profile.adresse || '',
        ville: this.profile.ville || '',
        bio: this.profile.bio || '',
        linkedin: this.profile.linkedin || '',
        github: this.profile.github || '',
        experiences: (this.profile.experiences || []).map((experience: StudentExperience) => ({ ...experience })),
        projects: (this.profile.projects || []).map((project: StudentProject) => ({ ...project })),
        languages: (this.profile.languages || []).map((language: StudentLanguage) => ({ ...language }))
      };
    } else {
      this.editCompanyData = {
        nom_entreprise: this.profile.nom_entreprise || '',
        secteur_activite: this.profile.secteur_activite || '',
        adresse: this.profile.adresse || '',
        description: this.profile.description || '',
        ville: this.profile.ville || '',
        telephone: this.profile.telephone || '',
        site_web: this.profile.site_web || '',
        linkedin: this.profile.linkedin || ''
      };
    }
  }

  cancelEdit(): void {
    this.isEditMode = false;
    this.errorMessage = '';
    this.successMessage = '';
  }

  onUpdate(): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.successMessage = '';

    const data = this.user.role === 'etudiant'
      ? {
          ...this.editStudentData,
          experiences: this.editStudentData.experiences.filter((experience) => experience.poste.trim() && experience.entreprise.trim()),
          projects: this.editStudentData.projects.filter((project) => project.nom.trim()),
          languages: this.editStudentData.languages.filter((language) => language.nom.trim())
        }
      : this.editCompanyData;

    console.log('Updating profile data:', data);

    this.profileService.updateProfile(data).subscribe({
      next: (response) => {
        console.log('Profile updated successfully:', response);
        this.isLoading = false;
        this.successMessage = response.message;
        this.profile = response.profile;
        this.updateCompletion(response);
        // Exit edit mode after successful update
        setTimeout(() => {
          this.isEditMode = false;
          this.successMessage = '';
        }, 1500);
      },
      error: (error) => {
        console.error('Error updating profile:', error);
        console.error('Error status:', error.status);
        console.error('Error message:', error.error?.message);
        console.error('Error errors:', error.error?.errors);

        this.isLoading = false;
        if (error.status === 401) {
          this.handleUnauthorized();
        } else if (error.status === 0) {
          this.errorMessage = 'Le serveur Laravel est inaccessible. Démarrez le backend sur le port 8000 puis réessayez.';
        } else if (error.error && error.error.errors) {
          this.errorMessage = Object.values(error.error.errors).flat().join(', ');
        } else if (error.error && error.error.message) {
          this.errorMessage = error.error.message;
        } else {
          this.errorMessage = `Erreur lors de la mise à jour du profil (Status: ${error.status})`;
        }
      }
    });
  }

  onSubmit(): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.successMessage = '';

    const data = this.user.role === 'etudiant' ? this.studentData : this.companyData;

    console.log('Submitting profile data:', data);
    console.log('User role:', this.user.role);

    this.profileService.createProfile(data).subscribe({
      next: (response) => {
        console.log('Profile created successfully:', response);
        this.isLoading = false;
        this.successMessage = response.message;
        // Switch to dashboard after successful creation
        setTimeout(() => {
          this.checkProfile();
          this.successMessage = '';
        }, 1500);
      },
      error: (error) => {
        console.error('Error creating profile:', error);
        console.error('Error status:', error.status);
        console.error('Error message:', error.error?.message);
        console.error('Error errors:', error.error?.errors);

        this.isLoading = false;
        if (error.status === 401) {
          this.handleUnauthorized();
        } else if (error.status === 0) {
          this.errorMessage = 'Le serveur Laravel est inaccessible. Démarrez le backend sur le port 8000 puis réessayez.';
        } else if (error.error && error.error.errors) {
          this.errorMessage = Object.values(error.error.errors).flat().join(', ');
        } else if (error.error && error.error.message) {
          this.errorMessage = error.error.message;
        } else {
          this.errorMessage = `Erreur lors de la création du profil (Status: ${error.status})`;
        }
      }
    });
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {
        this.authService.clearToken();
        this.router.navigate(['']);
      },
      error: (error) => {
        console.error('Logout error:', error);
        this.authService.clearToken();
        this.router.navigate(['']);
      }
    });
  }

  getRoleLabel(): string {
    return this.user?.role === 'etudiant' ? 'Étudiant' : 'Entreprise';
  }

  private handleUnauthorized(): void {
    this.authService.clearToken();
    this.errorMessage = 'Votre session a expiré. Reconnectez-vous pour continuer.';
    this.router.navigate(['/']);
  }

  getInitials(): string {
    if (!this.user) return '';
    const first = this.user.first_name?.charAt(0) || '';
    const last = this.user.last_name?.charAt(0) || '';
    return (first + last).toUpperCase();
  }

  getTotalPortfolioItems(): number {
    return (this.profile?.experiences?.length || 0)
      + (this.profile?.projects?.length || 0)
      + (this.profile?.languages?.length || 0);
  }

  addExperience(): void {
    this.editStudentData.experiences.push({
      poste: '',
      entreprise: '',
      lieu: '',
      date_debut: '',
      date_fin: '',
      description: ''
    });
  }

  removeExperience(index: number): void {
    this.editStudentData.experiences.splice(index, 1);
  }

  addProject(): void {
    this.editStudentData.projects.push({ nom: '', technologies: '', lien: '', description: '' });
  }

  removeProject(index: number): void {
    this.editStudentData.projects.splice(index, 1);
  }

  addLanguage(): void {
    this.editStudentData.languages.push({ nom: '', niveau: 'Intermédiaire' });
  }

  removeLanguage(index: number): void {
    this.editStudentData.languages.splice(index, 1);
  }

  triggerPhotoUpload(): void {
    const photoInput = document.getElementById('photo-input') as HTMLInputElement;
    if (photoInput) {
      photoInput.click();
    }
  }

  triggerCvUpload(): void {
    const cvInput = document.getElementById('cv-input') as HTMLInputElement;
    if (cvInput) {
      cvInput.click();
    }
  }

  onPhotoSelected(event: Event): void {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;

    const file = input.files?.[0];
    if (!file) return;

    input.value = '';

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const hasSupportedExtension = /\.(jpe?g|png|webp)$/i.test(file.name);
    if (!allowedTypes.includes(file.type) && !hasSupportedExtension) {
      this.errorMessage = 'Format non pris en charge. Choisissez une image JPG, PNG ou WebP.';
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      this.errorMessage = 'La photo ne doit pas dépasser 2 Mo.';
      return;
    }

    const previewUrl = URL.createObjectURL(file);
    this.photoPreview = previewUrl;
    this.errorMessage = '';

    this.isUploadingPhoto = true;
    this.profileService.uploadPhoto(file).subscribe({
      next: (response) => {
        console.log('Photo uploaded successfully:', response);
        this.isUploadingPhoto = false;
        this.successMessage = response.message;
        if (response.photo_url) {
          this.profile.photo_url = response.photo_url;
        } else if (response.profile?.['photo_url']) {
          this.profile.photo_url = response.profile['photo_url'];
        }
        this.profileCompletionPercentage = response.completion_percentage ?? this.profileCompletionPercentage;
        this.refreshCompletion();
        URL.revokeObjectURL(previewUrl);
        this.photoPreview = null;
        setTimeout(() => {
          this.successMessage = '';
        }, 2000);
      },
      error: (error) => {
        console.error('Error uploading photo:', error);
        this.isUploadingPhoto = false;
        URL.revokeObjectURL(previewUrl);
        this.photoPreview = null;
        if (error.error && error.error.errors) {
          this.errorMessage = Object.values(error.error.errors).flat().join(', ');
        } else if (error.error && error.error.message) {
          this.errorMessage = error.error.message;
        } else if (error.status === 0) {
          this.errorMessage = 'Le serveur est inaccessible. Vérifiez que Laravel est démarré.';
        } else {
          this.errorMessage = 'Erreur lors du téléchargement de la photo';
        }
      }
    });
  }

  loadSkills(): void {
    this.isLoadingSkills = true;
    this.skillService.getSkills().subscribe({
      next: (response) => {
        if (response.success) {
          this.skills = response.skills || [];
        } else {
          this.errorMessage = 'Impossible de charger vos compétences.';
        }
        this.isLoadingSkills = false;
      },
      error: (error) => {
        console.error('Error loading skills:', error);
        this.errorMessage = error.error?.message || 'Impossible de charger vos compétences.';
        this.isLoadingSkills = false;
      }
    });
  }

  addSkill(): void {
    if (!this.newSkill.name.trim()) return;

    this.isAddingSkill = true;
    this.skillService.createSkill(this.newSkill).subscribe({
      next: (response) => {
        if (response.success) {
          this.skills.push(response.skill);
          this.newSkill = { name: '', level: 'intermédiaire' };
          this.successMessage = 'Compétence ajoutée avec succès';
          this.refreshCompletion();
          setTimeout(() => this.successMessage = '', 2000);
        } else {
          this.errorMessage = response.message || 'Erreur lors de l\'ajout de la compétence';
        }
        this.isAddingSkill = false;
      },
      error: (error) => {
        console.error('Error adding skill:', error);
        this.errorMessage = error.error?.message || 'Erreur lors de l\'ajout de la compétence';
        this.isAddingSkill = false;
      }
    });
  }

  startEditingSkill(skill: ProfileSkill): void {
    this.editingSkillId = skill.id;
    this.editSkillData = { name: skill.name, level: skill.level };
    this.errorMessage = '';
  }

  cancelSkillEdit(): void {
    this.editingSkillId = null;
    this.editSkillData = { name: '', level: 'intermédiaire' };
  }

  saveSkillEdit(id: number): void {
    const name = this.editSkillData.name.trim();
    if (!name) {
      this.errorMessage = 'Le nom de la compétence est obligatoire.';
      return;
    }

    this.isUpdatingSkillId = id;
    this.errorMessage = '';
    this.skillService.updateSkill(id, { ...this.editSkillData, name }).subscribe({
      next: (response) => {
        if (response.success && response.skill) {
          this.skills = this.skills.map((skill) => skill.id === id ? response.skill : skill);
          this.cancelSkillEdit();
          this.successMessage = response.message || 'Compétence mise à jour avec succès';
          this.refreshCompletion();
          setTimeout(() => this.successMessage = '', 2000);
        } else {
          this.errorMessage = response.message || 'Erreur lors de la mise à jour de la compétence';
        }
        this.isUpdatingSkillId = null;
      },
      error: (error) => {
        console.error('Error updating skill:', error);
        this.errorMessage = error.error?.message || 'Erreur lors de la mise à jour de la compétence';
        this.isUpdatingSkillId = null;
      }
    });
  }

  deleteSkill(id: number): void {
    this.skillService.deleteSkill(id).subscribe({
      next: (response) => {
        if (response.success) {
          this.skills = this.skills.filter(s => s.id !== id);
          this.successMessage = 'Compétence supprimée avec succès';
          this.refreshCompletion();
          setTimeout(() => this.successMessage = '', 2000);
        } else {
          this.errorMessage = response.message || 'Erreur lors de la suppression de la compétence';
        }
      },
      error: (error) => {
        console.error('Error deleting skill:', error);
        this.errorMessage = error.error?.message || 'Erreur lors de la suppression de la compétence';
      }
    });
  }

  loadCvs(): void {
    this.isLoadingCv = true;
    this.cvService.getCvs().subscribe({
      next: (response) => {
        if (response.success) {
          this.cvs = response.cvs || [];
        }
        this.isLoadingCv = false;
      },
      error: (error) => {
        console.error('Error loading CVs:', error);
        this.isLoadingCv = false;
      }
    });
  }

  onCvSelected(event: Event): void {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;

    const file = input.files?.[0];
    if (!file) return;
    input.value = '';

    if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
      this.errorMessage = 'Seuls les fichiers PDF sont acceptés.';
      return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      this.errorMessage = 'La taille du fichier ne doit pas dépasser 5MB.';
      return;
    }

    this.isUploadingCv = true;
    this.errorMessage = '';

    this.cvService.uploadCv(file).subscribe({
      next: (response) => {
        if (response.success) {
          this.cvs = [response.cv];
          this.successMessage = 'CV téléchargé avec succès';
          this.refreshCompletion();
          setTimeout(() => this.successMessage = '', 2000);
        } else {
          this.errorMessage = response.message || 'Erreur lors du téléchargement du CV';
        }
        this.isUploadingCv = false;
      },
      error: (error) => {
        console.error('Error uploading CV:', error);
        this.errorMessage = error.error?.message || 'Erreur lors du téléchargement du CV';
        this.isUploadingCv = false;
      }
    });
  }

  deleteCv(id: number): void {
    this.cvService.deleteCv(id).subscribe({
      next: (response) => {
        if (response.success) {
          this.cvs = this.cvs.filter(cv => cv.id !== id);
          this.successMessage = 'CV supprimé avec succès';
          this.refreshCompletion();
          setTimeout(() => this.successMessage = '', 2000);
        }
      },
      error: (error) => {
        console.error('Error deleting CV:', error);
        this.errorMessage = 'Erreur lors de la suppression du CV';
      }
    });
  }

  analyzeProfile(): void {
    if (this.user?.role !== 'etudiant') {
      this.errorMessage = 'Cette fonctionnalité est réservée aux étudiants';
      return;
    }

    if (!this.hasProfile) {
      this.errorMessage = 'Veuillez créer votre profil avant de demander une analyse IA';
      return;
    }

    this.isAnalyzing = true;
    this.errorMessage = '';
    this.showAnalysis = false;

    this.profileAnalysisService.analyzeProfile().subscribe({
      next: (response) => {
        if (response.success && response.analysis) {
          this.analysis = response.analysis;
          this.showAnalysis = true;
          this.successMessage = 'Analyse du profil générée avec succès';
          setTimeout(() => this.successMessage = '', 3000);
        } else {
          this.errorMessage = response.message || 'Erreur lors de l\'analyse du profil';
        }
        this.isAnalyzing = false;
      },
      error: (error) => {
        console.error('Error analyzing profile:', error);
        this.isAnalyzing = false;
        if (error.error && error.error.message) {
          this.errorMessage = error.error.message;
        } else if (error.status === 0) {
          this.errorMessage = 'Le serveur est inaccessible. Vérifiez que Laravel est démarré.';
        } else {
          this.errorMessage = 'Erreur lors de l\'analyse du profil. Veuillez réessayer.';
        }
      }
    });
  }

  closeAnalysis(): void {
    this.showAnalysis = false;
    this.analysis = null;
  }

  onSidebarToggle(): void {
    this.isSidebarCollapsed = !this.isSidebarCollapsed;
  }

  @HostListener('window:resize')
  onWindowResize(): void {
    if (window.innerWidth <= 1024) {
      this.isSidebarCollapsed = true;
    }
  }

  onSidebarLogout(): void {
    this.logout();
  }

  showSuccessNotification(title: string, message: string): void {
    this.notificationType = 'success';
    this.notificationTitle = title;
    this.notificationMessage = message;
    this.showNotification = true;
  }

  showErrorNotification(title: string, message: string): void {
    this.notificationType = 'error';
    this.notificationTitle = title;
    this.notificationMessage = message;
    this.showNotification = true;
  }

  onNotificationClose(): void {
    this.showNotification = false;
  }
}
