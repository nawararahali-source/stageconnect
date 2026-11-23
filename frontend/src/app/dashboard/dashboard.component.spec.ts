import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Application, ApplicationService } from '../services/application.service';
import { AuthService } from '../services/auth.service';
import { CvService } from '../services/cv.service';
import { InternshipOffer, InternshipOfferService } from '../services/internship-offer.service';
import { MatchingService } from '../services/matching.service';
import { DashboardStatsService } from '../services/dashboard-stats.service';
import { ProfileService } from '../services/profile.service';
import { SkillService } from '../services/skill.service';
import { of } from 'rxjs';
import { DashboardComponent } from './dashboard.component';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        { provide: AuthService, useValue: {} },
        { provide: ApplicationService, useValue: {} },
        { provide: ProfileService, useValue: {} },
        { provide: SkillService, useValue: {} },
        { provide: CvService, useValue: {} },
        { provide: InternshipOfferService, useValue: {} },
        { provide: MatchingService, useValue: {} },
        { provide: DashboardStatsService, useValue: {} },
        provideRouter([])
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
  });

  it('filters available offers by keywords, work mode, and nullable skills', () => {
    component.offers = [
      createOffer(1, {
        title: 'Stage Angular',
        location: 'Tunis',
        work_mode: 'remote',
        required_skills: ['TypeScript', 'Angular']
      }),
      createOffer(2, {
        title: 'Stage Laravel',
        location: 'Sfax',
        work_mode: 'onsite',
        required_skills: null
      })
    ];
    component.offerSearchTerm = 'angular';
    component.offerWorkMode = 'remote';

    expect(component.filteredStudentOffers.map((offer) => offer.id)).toEqual([1]);

    component.offerSearchTerm = 'laravel';
    component.offerWorkMode = 'all';
    expect(component.filteredStudentOffers.map((offer) => offer.id)).toEqual([2]);
  });

  it('derives application statistics from real application statuses', () => {
    component.applications = [
      createApplication(1, 'pending'),
      createApplication(2, 'accepted'),
      createApplication(3, 'rejected')
    ];

    expect(component.pendingApplicationsCount).toBe(1);
    expect(component.acceptedApplicationsCount).toBe(1);
    expect(component.hasApplicationsForOffer(12)).toBeTrue();
    expect(component.hasApplicationsForOffer(99)).toBeFalse();
  });

  it('matches selected offer and result IDs even when API IDs are strings', () => {
    component.applications = [
      Object.assign(createApplication(1, 'accepted'), { internship_offer_id: '12' })
    ];
    component.offers = [
      createOffer(12, { title: 'Offre avec candidature' }),
      createOffer(99, { title: 'Offre sans candidature' })
    ];
    component.matchingResults = [
      {
        application_id: '1',
        student_id: 1,
        student_name: 'Student Test',
        score: 82,
        matching_skills: [],
        missing_skills: [],
        explanation: [],
        source: 'ml',
        contributions: {},
        criteria: {
          skills: { weight: 50, applicable: true, score: 40 },
          domain: { weight: 20, applicable: true, score: 20 },
          projects_experiences: { weight: 15, applicable: true, score: 10 },
          education_level: { weight: 10, applicable: true, score: 8 },
          location: { weight: 5, applicable: true, score: 5 }
        }
      }
    ];

    expect(component.hasApplicationsForOffer(12)).toBeTrue();
    expect(component.matchingOffers.map((offer) => offer.id)).toEqual([12]);
    expect(component.getMatchingDetails(1)?.score).toBe(82);
  });

  it('requests and displays matching results for the selected offer', () => {
    component.applications = [createApplication(1, 'accepted')];
    component.selectedOfferForMatching = 12;

    const matchingService = TestBed.inject(MatchingService);
    spyOn(matchingService, 'getOfferMatching').and.returnValue(of({
      success: true,
      offer_id: 12,
      offer_title: 'Stage',
      matching_results: [
        {
          application_id: 1,
          student_id: 1,
          student_name: 'Student Test',
          score: 82,
          matching_skills: [],
          missing_skills: [],
          explanation: ['Domaine correspondant'],
          source: 'ml',
          contributions: {},
          criteria: {
            skills: { weight: 50, applicable: true, score: 40 },
            domain: { weight: 20, applicable: true, score: 20 },
            projects_experiences: { weight: 15, applicable: true, score: 10 },
            education_level: { weight: 10, applicable: true, score: 8 },
            location: { weight: 5, applicable: true, score: 5 }
          }
        }
      ]
    }));

    component.analyzeSelectedOffer();

    expect(matchingService.getOfferMatching).toHaveBeenCalledWith(12);
    expect(component.showMatchingDetails).toBeTrue();
    expect(component.getMatchingScore(1)).toBe(82);
    expect(component.getMatchingDetails(1)?.explanation).toEqual(['Domaine correspondant']);
  });

  it('generates and displays an editable cover letter for the selected offer', () => {
    const offer = createOffer(12);
    const letter = 'Lettre de motivation générée par Gemini.';
    const offerService = TestBed.inject(InternshipOfferService);
    const generateCoverLetter = jasmine.createSpy('generateCoverLetter').and.returnValue(of({
      success: true,
      message: 'Lettre générée.',
      cover_letter: letter
    }));
    offerService.generateCoverLetter = generateCoverLetter;

    component.generateCoverLetter(offer);

    expect(generateCoverLetter).toHaveBeenCalledWith(12);
    expect(component.expandedOfferForDetails).toBe(12);
    expect(component.coverLetter).toBe(letter);
    expect(component.isGeneratingCoverLetter).toBeFalse();
  });

  it('formats the minimum deadline as the local YYYY-MM-DD date', () => {
    const today = new Date();
    const expected = [
      today.getFullYear(),
      String(today.getMonth() + 1).padStart(2, '0'),
      String(today.getDate()).padStart(2, '0')
    ].join('-');

    expect(component.minimumDeadline).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(component.minimumDeadline).toBe(expected);
  });

  it('rejects an expired deadline without trying to save the offer', () => {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    component.offerData.deadline = [
      yesterday.getFullYear(),
      String(yesterday.getMonth() + 1).padStart(2, '0'),
      String(yesterday.getDate()).padStart(2, '0')
    ].join('-');

    const offerService = TestBed.inject(InternshipOfferService);
    const createOffer = jasmine.createSpy('createOffer');
    const updateOffer = jasmine.createSpy('updateOffer');
    offerService.createOffer = createOffer;
    offerService.updateOffer = updateOffer;

    component.saveOffer();

    expect(createOffer).not.toHaveBeenCalled();
    expect(updateOffer).not.toHaveBeenCalled();
    expect(component.offerErrorMessage).toBe("La date limite doit être aujourd'hui ou une date ultérieure.");
  });
});

function createOffer(id: number, overrides: Partial<InternshipOffer> = {}): InternshipOffer {
  return {
    id,
    title: 'Stage',
    domain: 'Informatique',
    description: 'Description',
    duration: '3 mois',
    location: 'Tunis',
    work_mode: 'hybrid',
    required_skills: [],
    positions: 1,
    accepted_applications_count: 0,
    available_positions: 1,
    deadline: null,
    status: 'open',
    ...overrides
  };
}

function createApplication(id: number, status: Application['status']): Application {
  return {
    id,
    student_id: 1,
    internship_offer_id: 12,
    status,
    applied_at: '',
    created_at: '',
    updated_at: ''
  };
}
