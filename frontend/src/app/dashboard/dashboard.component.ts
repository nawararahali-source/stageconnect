import { Component, HostListener, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { Application, ApplicationService, StudentCv } from '../services/application.service';
import { CvService } from '../services/cv.service';
import { InternshipOffer, InternshipOfferData, InternshipOfferService } from '../services/internship-offer.service';
import { ProfileService } from '../services/profile.service';
import { SkillService } from '../services/skill.service';
import { MatchingResult, MatchingService } from '../services/matching.service';
import {
  ApplicationStatusCounts,
  CompanyDashboardStats,
  DashboardStatsService,
  StudentDashboardStats
} from '../services/dashboard-stats.service';
import { BaseChartDirective } from 'ng2-charts';
import { ChartData, ChartOptions } from 'chart.js';
import { SidebarComponent } from '../shared/sidebar.component';
import { StatCardComponent } from '../shared/stat-card.component';
import { ProgressCircleComponent } from '../shared/progress-circle.component';
import { NotificationToastComponent } from '../shared/notification-toast.component';
import { LoadingSkeletonComponent } from '../shared/loading-skeleton.component';
import { MessageService } from '../services/message.service';
import { interval, Subscription } from 'rxjs';

interface DashboardUser {
  first_name: string;
  last_name: string;
  email: string;
  role: string;
}

interface DashboardProfile {
  photo_url?: string | null;
  completion_percentage?: number;
  missing_items?: string[];
  formation?: string;
  specialite?: string;
  niveau?: string;
  telephone?: string;
  ville?: string;
  bio?: string;
  linkedin?: string;
  github?: string;
  nom_entreprise?: string;
  secteur_activite?: string;
  description?: string;
  experiences?: Array<{
    poste: string;
    entreprise: string;
    lieu?: string;
    date_debut: string;
    date_fin?: string;
    description?: string;
  }>;
  projects?: Array<{
    nom: string;
    technologies?: string;
    lien?: string;
    description?: string;
  }>;
  languages?: Array<{ nom: string; niveau: string }>;
}

interface DashboardSkill {
  id: number;
  name: string;
  level: string;
}

interface DashboardCv {
  id: number;
  file_name: string;
  download_url: string;
  file_size?: number;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    SidebarComponent,
    StatCardComponent,
    ProgressCircleComponent,
    NotificationToastComponent,
    LoadingSkeletonComponent,
    BaseChartDirective
  ],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css'
})
export class DashboardComponent implements OnInit, OnDestroy {
  user: DashboardUser | null = null;
  profile: DashboardProfile | null = null;
  skills: DashboardSkill[] = [];
  cvs: DashboardCv[] = [];
  offers: InternshipOffer[] = [];
  hasProfile = false;
  profileCompletionPercentage = 0;
  profileMissingItems: string[] = [];
  companyStats: CompanyDashboardStats | null = null;
  studentStats: StudentDashboardStats | null = null;
  isLoadingDashboardStats = false;
  dashboardStatsError = '';
  companyStatusChartData: ChartData<'doughnut', number[], string> = {
    labels: ['En attente', 'Acceptées', 'Refusées'],
    datasets: [{ data: [], backgroundColor: ['#f59e0b', '#10b981', '#ef4444'] }]
  };
  studentStatusChartData: ChartData<'doughnut', number[], string> = {
    labels: ['En attente', 'Acceptées', 'Refusées'],
    datasets: [{ data: [], backgroundColor: ['#f59e0b', '#10b981', '#ef4444'] }]
  };
  scoreDistributionChartData: ChartData<'bar', number[], string> = {
    labels: [],
    datasets: [{ data: [], label: 'Candidatures', backgroundColor: '#7c3aed', borderRadius: 6 }]
  };
  offerApplicationsChartData: ChartData<'bar', number[], string> = {
    labels: [],
    datasets: [{ data: [], label: 'Candidatures', backgroundColor: '#2563eb', borderRadius: 6 }]
  };
  readonly statusChartOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } }
  };
  readonly barChartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  };
  isLoadingProfile = true;
  isLoadingSkills = true;
  isLoadingCvs = true;
  isLoadingOffers = false;
  isLoadingApplications = false;
  isSavingOffer = false;
  isApplyingOfferId: number | null = null;
  expandedOfferForDetails: number | null = null;
  coverLetter = '';
  coverLetterErrorMessage = '';
  coverLetterCopied = false;
  isGeneratingCoverLetter = false;
  updatingApplicationId: number | null = null;
  openingConversationId: number | null = null;
  isOfferFormOpen = false;
  editingOfferId: number | null = null;
  offerMessage = '';
  offerErrorMessage = '';
  applicationMessage = '';
  applicationErrorMessage = '';
  applications: Application[] = [];
  matchingResults: MatchingResult[] = [];
  offerSearchTerm = '';
  offerWorkMode: 'all' | 'onsite' | 'hybrid' | 'remote' = 'all';
  isLoadingMatching = false;
  selectedOfferForMatching: number | null = null;
  showMatchingDetails: boolean = false;
  requiredSkillsInput = '';
  offerData: InternshipOfferData = this.emptyOfferForm();
  errorMessage = '';

  // Sidebar state
  isSidebarCollapsed = false;

  // Notification state
  showNotification = false;
  notificationType: 'success' | 'error' | 'warning' | 'info' = 'info';
  notificationTitle = '';
  notificationMessage = '';
  private studentDashboardRefresh?: Subscription;

  constructor(
    private authService: AuthService,
    private profileService: ProfileService,
    private skillService: SkillService,
    private cvService: CvService,
    private internshipOfferService: InternshipOfferService,
    private applicationService: ApplicationService,
    private matchingService: MatchingService,
    private dashboardStatsService: DashboardStatsService,
    private messageService: MessageService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.isSidebarCollapsed = window.innerWidth <= 1024;

    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['/'], { replaceUrl: true });
      return;
    }

    const storedUser = this.authService.getUser();
    if (storedUser) {
      this.setUser(storedUser);
    } else {
      this.authService.me().subscribe({
        next: (response) => {
          this.authService.setUser(response.user);
          this.setUser(response.user);
        },
        error: (error) => this.handleLoadError(error, 'Impossible de charger votre compte.')
      });
    }
  }

  ngOnDestroy(): void {
    this.studentDashboardRefresh?.unsubscribe();
  }

  get fullName(): string {
    return [this.user?.first_name, this.user?.last_name].filter(Boolean).join(' ');
  }

  get initials(): string {
    return `${this.user?.first_name?.charAt(0) || ''}${this.user?.last_name?.charAt(0) || ''}`.toUpperCase();
  }

  get isStudent(): boolean {
    return this.user?.role === 'etudiant';
  }

  get isCompany(): boolean {
    return this.user?.role === 'entreprise';
  }

  get portfolioCount(): number {
    return (this.profile?.experiences?.length || 0)
      + (this.profile?.projects?.length || 0)
      + (this.profile?.languages?.length || 0);
  }

  get filteredStudentOffers(): InternshipOffer[] {
    const searchTerm = this.offerSearchTerm.trim().toLocaleLowerCase();

    return this.offers.filter((offer) => {
      const matchesSearch = !searchTerm || [
        offer.title,
        offer.domain,
        offer.location,
        ...(offer.required_skills || [])
      ].some((value) => value?.toLocaleLowerCase().includes(searchTerm));
      const matchesWorkMode = this.offerWorkMode === 'all' || offer.work_mode === this.offerWorkMode;

      return matchesSearch && matchesWorkMode;
    });
  }

  get acceptedApplicationsCount(): number {
    return this.applications.filter((application) => application.status === 'accepted').length;
  }

  get pendingApplicationsCount(): number {
    return this.applications.filter((application) => application.status === 'pending').length;
  }

  get matchingOffers(): InternshipOffer[] {
    return this.offers.filter((offer) => this.hasApplicationsForOffer(offer.id));
  }

  get hasCompanyChartData(): boolean {
    return Boolean(this.companyStats
      && (this.totalApplications(this.companyStats.applications_by_status) > 0
        || this.companyStats.score_distribution.some((band) => band.count > 0)
        || this.companyStats.applications_by_offer.some((offer) => offer.count > 0)));
  }

  get hasStudentChartData(): boolean {
    return Boolean(this.studentStats
      && this.totalApplications(this.studentStats.applications_by_status) > 0);
  }

  get minimumDeadline(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  get offerFormTitle(): string {
    return this.editingOfferId ? "Modifier l'offre" : 'Nouvelle offre de stage';
  }

  get offerSubmitLabel(): string {
    if (this.isSavingOffer) {
      return 'Enregistrement…';
    }

    return this.editingOfferId ? 'Enregistrer les modifications' : "Publier l'offre";
  }

  hasApplicationsForOffer(offerId: number | string | null): boolean {
    return offerId !== null && this.applications.some(
      (application) => Number(application.internship_offer_id) === Number(offerId)
    );
  }

  onMatchingOfferChange(): void {
    this.matchingResults = [];
    this.showMatchingDetails = false;
    this.applicationErrorMessage = '';
  }

  analyzeSelectedOffer(): void {
    const offerId = Number(this.selectedOfferForMatching);
    if (!Number.isInteger(offerId) || offerId <= 0) {
      this.applicationErrorMessage = 'Sélectionnez une offre à analyser.';
      return;
    }

    if (!this.hasApplicationsForOffer(offerId)) {
      this.matchingResults = [];
      this.showMatchingDetails = false;
      this.applicationErrorMessage = 'Aucune candidature reçue pour cette offre.';
      return;
    }

    this.loadMatchingForOffer(offerId);
  }

  @HostListener('window:resize')
  onWindowResize(): void {
    if (window.innerWidth <= 1024) {
      this.isSidebarCollapsed = true;
    }
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.finishLogout(),
      error: (error) => {
        console.error('Logout error:', error);
        this.finishLogout();
      }
    });
  }

  openOfferForm(offer?: InternshipOffer): void {
    this.offerErrorMessage = '';
    this.offerMessage = '';
    this.editingOfferId = offer?.id ?? null;
    this.offerData = offer
      ? {
          title: offer.title,
          domain: offer.domain,
          description: offer.description,
          duration: offer.duration,
          location: offer.location,
          work_mode: offer.work_mode,
          required_skills: [...(offer.required_skills || [])],
          positions: offer.positions,
          deadline: offer.deadline,
          status: offer.status
        }
      : this.emptyOfferForm();
    this.requiredSkillsInput = offer?.required_skills?.join(', ') || '';
    this.isOfferFormOpen = true;
  }

  cancelOfferForm(): void {
    this.isOfferFormOpen = false;
    this.editingOfferId = null;
    this.offerErrorMessage = '';
    this.offerData = this.emptyOfferForm();
    this.requiredSkillsInput = '';
  }

  saveOffer(): void {
    if (this.offerData.deadline && this.offerData.deadline < this.minimumDeadline) {
      this.offerErrorMessage = "La date limite doit être aujourd'hui ou une date ultérieure.";
      return;
    }

    this.isSavingOffer = true;
    this.offerErrorMessage = '';
    this.offerMessage = '';
    const data: InternshipOfferData = {
      ...this.offerData,
      required_skills: [...new Set(this.requiredSkillsInput.split(',').map((skill) => skill.trim()).filter(Boolean))]
    };
    const request = this.editingOfferId
      ? this.internshipOfferService.updateOffer(this.editingOfferId, data)
      : this.internshipOfferService.createOffer(data);

    request.subscribe({
      next: (response) => {
        this.isSavingOffer = false;
        this.offerMessage = response.message;
        this.isOfferFormOpen = false;
        this.editingOfferId = null;
        this.loadCompanyOffers();
        this.loadDashboardStats();
      },
      error: (error) => {
        this.isSavingOffer = false;
        this.offerErrorMessage = this.validationMessage(error, 'Impossible d’enregistrer cette offre.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  deleteOffer(offer: InternshipOffer): void {
    if (!window.confirm(`Supprimer l’offre « ${offer.title} » ?`)) {
      return;
    }

    this.offerErrorMessage = '';
    this.offerMessage = '';
    this.internshipOfferService.deleteOffer(offer.id).subscribe({
      next: (response) => {
        this.offerMessage = response.message;
        this.loadCompanyOffers();
        this.loadDashboardStats();
      },
      error: (error) => {
        this.offerErrorMessage = this.validationMessage(error, 'Impossible de supprimer cette offre.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  hasApplied(offerId: number): boolean {
    return this.applications.some((application) => application.internship_offer_id === offerId);
  }

  applyToOffer(offer: InternshipOffer): void {
    if (this.hasApplied(offer.id) || this.isApplyingOfferId !== null) {
      return;
    }

    this.isApplyingOfferId = offer.id;
    this.applicationMessage = '';
    this.applicationErrorMessage = '';
    this.applicationService.applyToOffer(offer.id).subscribe({
      next: (response) => {
        this.isApplyingOfferId = null;
        this.applicationMessage = response.message;
        this.loadStudentApplications();
        this.loadStudentOffers();
      this.loadDashboardStats();
      },
      error: (error) => {
        this.isApplyingOfferId = null;
        this.applicationErrorMessage = this.validationMessage(error, 'Impossible d’envoyer votre candidature.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  toggleOfferDetails(offer: InternshipOffer): void {
    if (this.expandedOfferForDetails === offer.id) {
      this.expandedOfferForDetails = null;
      return;
    }

    this.expandedOfferForDetails = offer.id;
    this.coverLetter = '';
    this.coverLetterErrorMessage = '';
    this.coverLetterCopied = false;
  }

  generateCoverLetter(offer: InternshipOffer): void {
    this.expandedOfferForDetails = offer.id;
    this.isGeneratingCoverLetter = true;
    this.coverLetterErrorMessage = '';
    this.coverLetterCopied = false;

    this.internshipOfferService.generateCoverLetter(offer.id).subscribe({
      next: (response) => {
        this.isGeneratingCoverLetter = false;
        if (!response.cover_letter?.trim()) {
          this.coverLetterErrorMessage = 'Gemini n’a pas renvoyé de lettre. Veuillez réessayer.';
          return;
        }
        this.coverLetter = response.cover_letter;
      },
      error: (error) => {
        this.isGeneratingCoverLetter = false;
        this.coverLetterErrorMessage = this.validationMessage(
          error,
          'Impossible de générer la lettre de motivation. Veuillez réessayer.'
        );
        this.handleUnauthorizedError(error);
      }
    });
  }

  copyCoverLetter(): void {
    if (!this.coverLetter.trim()) {
      return;
    }

    if (!navigator.clipboard?.writeText) {
      this.coverLetterErrorMessage = 'La copie automatique n’est pas disponible dans ce navigateur.';
      return;
    }

    navigator.clipboard.writeText(this.coverLetter).then(() => {
      this.coverLetterCopied = true;
      this.coverLetterErrorMessage = '';
    }).catch(() => {
      this.coverLetterCopied = false;
      this.coverLetterErrorMessage = 'Impossible de copier la lettre. Sélectionnez son texte pour la copier.';
    });
  }

  updateApplicationStatus(application: Application, status: 'accepted' | 'rejected'): void {
    if (this.updatingApplicationId !== null) {
      return;
    }

    this.updatingApplicationId = application.id;
    this.applicationMessage = '';
    this.applicationErrorMessage = '';
    this.applicationService.updateApplicationStatus(application.id, status).subscribe({
      next: (response) => {
        this.updatingApplicationId = null;
        this.applicationMessage = response.message;
        this.loadCompanyApplications();
        this.loadCompanyOffers();
        this.loadDashboardStats();
      },
      error: (error) => {
        this.updatingApplicationId = null;
        this.applicationErrorMessage = this.validationMessage(error, 'Impossible de modifier le statut de cette candidature.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  openApplicationConversation(application: Application): void {
    if (this.openingConversationId !== null) {
      return;
    }

    this.openingConversationId = application.id;
    this.applicationErrorMessage = '';
    this.messageService.startConversation(application.id).subscribe({
      next: (response) => {
        this.openingConversationId = null;
        this.router.navigate(['/messages'], {
          queryParams: { conversation: response.conversation.id }
        });
      },
      error: (error) => {
        this.openingConversationId = null;
        this.applicationErrorMessage = this.validationMessage(
          error,
          'Impossible d’ouvrir la conversation liée à cette candidature.'
        );
        this.handleUnauthorizedError(error);
      }
    });
  }

  getCvDownloadUrl(cv: StudentCv): string {
    return this.applicationService.getCvDownloadUrl(cv);
  }

  loadMatchingForOffer(offerId: number): void {
    this.isLoadingMatching = true;
    this.selectedOfferForMatching = offerId;
    this.showMatchingDetails = false;
    this.applicationErrorMessage = '';
    
    this.matchingService.getOfferMatching(offerId).subscribe({
      next: (response) => {
        this.matchingResults = response.matching_results || [];
        this.isLoadingMatching = false;
        this.showMatchingDetails = true;
        if (!this.matchingResults.length) {
          this.applicationErrorMessage = 'Aucun résultat de compatibilité pour cette offre.';
        }
      },
      error: (error) => {
        this.isLoadingMatching = false;
        if (error.status === 0) {
          this.applicationErrorMessage = 'Impossible de joindre Laravel sur 127.0.0.1:8000. Vérifiez que le backend est démarré.';
        } else if (error.status === 401) {
          this.applicationErrorMessage = 'Votre session a expiré. Reconnectez-vous puis relancez l’analyse.';
        } else if (error.status === 403) {
          this.applicationErrorMessage = 'Seul un compte entreprise peut analyser les candidatures.';
        } else {
          this.applicationErrorMessage = this.validationMessage(error, 'Impossible de charger les scores de matching.');
        }
        this.handleUnauthorizedError(error);
      }
    });
  }

  getMatchingScore(applicationId: number): number {
    const result = this.getMatchingDetails(applicationId);
    return result?.score || 0;
  }

  getMatchingDetails(applicationId: number): MatchingResult | null {
    return this.matchingResults.find(
      (result) => Number(result.application_id) === Number(applicationId)
    ) || null;
  }

  getContributionLabel(feature: string): string {
    const labels: Record<string, string> = {
      skills_match_ratio: 'Correspondance des compétences',
      nb_skills_matched: 'Compétences correspondantes',
      avg_skill_level: 'Niveau moyen des compétences',
      domain_match: 'Correspondance du domaine',
      nb_projects: 'Projets',
      nb_experiences: 'Expériences',
      study_level: 'Niveau d’études',
      location_match: 'Localisation',
      tfidf_similarity: 'Similarité du profil et de l’offre'
    };
    return labels[feature] || feature;
  }

  getScoreColor(score: number): string {
    if (score >= 70) return '#10b981'; // green
    if (score >= 50) return '#f59e0b'; // orange
    return '#ef4444'; // red
  }

  getScoreLabel(score: number): string {
    if (score >= 70) return 'Excellent';
    if (score >= 50) return 'Bon';
    if (score >= 30) return 'Moyen';
    return 'Faible';
  }

  toggleMatchingDetails(): void {
    this.showMatchingDetails = !this.showMatchingDetails;
  }

  onSidebarToggle(): void {
    this.isSidebarCollapsed = !this.isSidebarCollapsed;
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

  private setUser(user: DashboardUser): void {
    this.user = user;
    this.loadProfile();
    this.loadDashboardStats();

    if (this.isStudent) {
      this.loadSkills();
      this.loadCvs();
      this.loadStudentOffers();
      this.loadStudentApplications();
      this.studentDashboardRefresh ??= interval(15000).subscribe(() => {
        if (document.visibilityState !== 'visible' || this.isLoadingOffers || this.isLoadingApplications) {
          return;
        }

        this.loadStudentOffers(false);
        this.loadStudentApplications(false);
      });
    } else {
      this.isLoadingSkills = false;
      this.isLoadingCvs = false;
      this.loadCompanyOffers();
      this.loadCompanyApplications();
    }
  }

  private loadDashboardStats(): void {
    this.isLoadingDashboardStats = true;
    this.dashboardStatsError = '';
    const statsRequest = this.isStudent
      ? this.dashboardStatsService.getStudentStats()
      : this.dashboardStatsService.getCompanyStats();

    statsRequest.subscribe({
      next: (stats) => {
        if (this.isStudent) {
          this.studentStats = stats as StudentDashboardStats;
          this.studentStatusChartData = this.statusChart(this.studentStats.applications_by_status);
        } else {
          this.companyStats = stats as CompanyDashboardStats;
          this.companyStatusChartData = this.statusChart(this.companyStats.applications_by_status);
          this.scoreDistributionChartData = {
            labels: this.companyStats.score_distribution.map((band) => band.label),
            datasets: [{
              data: this.companyStats.score_distribution.map((band) => band.count),
              label: 'Candidatures',
              backgroundColor: '#7c3aed',
              borderRadius: 6
            }]
          };
          this.offerApplicationsChartData = {
            labels: this.companyStats.applications_by_offer.map((offer) => offer.offer_title),
            datasets: [{
              data: this.companyStats.applications_by_offer.map((offer) => offer.count),
              label: 'Candidatures',
              backgroundColor: '#2563eb',
              borderRadius: 6
            }]
          };
        }
        this.isLoadingDashboardStats = false;
      },
      error: (error) => {
        this.isLoadingDashboardStats = false;
        this.dashboardStatsError = error.error?.message
          || 'Impossible de charger les statistiques du tableau de bord.';
        this.handleUnauthorizedError(error);
      }
    });
  }

  private statusChart(statuses: ApplicationStatusCounts): ChartData<'doughnut', number[], string> {
    return {
      labels: ['En attente', 'Acceptées', 'Refusées'],
      datasets: [{
        data: [statuses.pending, statuses.accepted, statuses.rejected],
        backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
        borderWidth: 0
      }]
    };
  }

  private totalApplications(statuses: ApplicationStatusCounts): number {
    return statuses.pending + statuses.accepted + statuses.rejected;
  }

  private loadStudentOffers(showLoading = true): void {
    if (showLoading) {
      this.isLoadingOffers = true;
    }
    this.applicationService.getOffers().subscribe({
      next: (response) => {
        this.offers = response.offers || [];
        this.isLoadingOffers = false;
      },
      error: (error) => {
        this.isLoadingOffers = false;
        this.applicationErrorMessage = this.validationMessage(error, 'Impossible de charger les offres de stage.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  private loadStudentApplications(showLoading = true): void {
    if (showLoading) {
      this.isLoadingApplications = true;
    }
    this.applicationService.getMyApplications().subscribe({
      next: (response) => {
        this.applications = response.applications || [];
        this.isLoadingApplications = false;
      },
      error: (error) => {
        this.isLoadingApplications = false;
        this.applicationErrorMessage = this.validationMessage(error, 'Impossible de charger vos candidatures.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  private loadCompanyApplications(): void {
    this.isLoadingApplications = true;
    this.applicationService.getCompanyApplications().subscribe({
      next: (response) => {
        this.applications = response.applications || [];
        this.isLoadingApplications = false;
        this.selectDefaultMatchingOffer();
      },
      error: (error) => {
        this.isLoadingApplications = false;
        this.applicationErrorMessage = this.validationMessage(error, 'Impossible de charger les candidatures reçues.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  private loadCompanyOffers(): void {
    this.isLoadingOffers = true;
    this.internshipOfferService.getOffers().subscribe({
      next: (response) => {
        this.offers = response.offers || [];
        this.isLoadingOffers = false;
        this.selectDefaultMatchingOffer();
      },
      error: (error) => {
        this.isLoadingOffers = false;
        this.offerErrorMessage = this.validationMessage(error, 'Impossible de charger les offres de stage.');
        this.handleUnauthorizedError(error);
      }
    });
  }

  private loadProfile(): void {
    this.profileService.getProfile().subscribe({
      next: (response) => {
        this.profile = response.profile || null;
        this.hasProfile = Boolean(this.profile);
        this.profileCompletionPercentage = response.completion_percentage
          ?? this.profile?.completion_percentage
          ?? 0;
        this.profileMissingItems = response.missing_items
          ?? this.profile?.missing_items
          ?? [];
        this.isLoadingProfile = false;
      },
      error: (error) => {
        if (error.status === 404) {
          this.hasProfile = false;
          this.profileCompletionPercentage = error.error?.completion_percentage ?? 0;
          this.profileMissingItems = error.error?.missing_items ?? [];
          this.isLoadingProfile = false;
          return;
        }
        this.handleLoadError(error, 'Impossible de charger votre profil.');
        this.isLoadingProfile = false;
      }
    });
  }

  getProfileCompletionFragment(item: string): string {
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
    return 'profile';
  }

  private loadSkills(): void {
    this.skillService.getSkills().subscribe({
      next: (response) => {
        this.skills = response.skills || [];
        this.isLoadingSkills = false;
      },
      error: (error) => {
        this.handleLoadError(error, 'Impossible de charger vos compétences.');
        this.isLoadingSkills = false;
      }
    });
  }

  private loadCvs(): void {
    this.cvService.getCvs().subscribe({
      next: (response) => {
        this.cvs = response.cvs || [];
        this.isLoadingCvs = false;
      },
      error: (error) => {
        this.handleLoadError(error, 'Impossible de charger vos CV.');
        this.isLoadingCvs = false;
      }
    });
  }

  private handleLoadError(error: { status?: number }, message: string): void {
    console.error(message, error);
    if (error.status === 401) {
      this.authService.clearToken();
      this.router.navigate(['/'], { replaceUrl: true });
      return;
    }
    this.errorMessage = message;
  }

  private handleUnauthorizedError(error: { status?: number }): void {
    if (error.status === 401) {
      this.authService.clearToken();
      this.router.navigate(['/'], { replaceUrl: true });
    }
  }

  private selectDefaultMatchingOffer(): void {
    const selectableOffers = this.matchingOffers;
    const selectedOfferStillAvailable = selectableOffers.some(
      (offer) => Number(offer.id) === Number(this.selectedOfferForMatching)
    );

    if (!selectedOfferStillAvailable) {
      this.selectedOfferForMatching = selectableOffers[0]?.id ?? null;
      this.matchingResults = [];
      this.showMatchingDetails = false;
    }
  }

  private validationMessage(error: { status?: number; error?: { message?: string; errors?: Record<string, string[]> } }, fallback: string): string {
    if (error.error?.errors) {
      const messages = Object.values(error.error.errors).flat();
      if (messages.some((message) => message.includes('deadline must be a date after or equal to today'))) {
        return "La date limite doit être aujourd'hui ou une date ultérieure.";
      }
      return messages.join(' ');
    }
    return error.error?.message || fallback;
  }

  private emptyOfferForm(): InternshipOfferData {
    return {
      title: '',
      domain: '',
      description: '',
      duration: '',
      location: '',
      work_mode: 'onsite',
      required_skills: [],
      positions: 1,
      deadline: null,
      status: 'open'
    };
  }

  private finishLogout(): void {
    this.authService.clearToken();
    this.router.navigate(['/'], { replaceUrl: true });
  }
}
