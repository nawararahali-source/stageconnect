import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface StudentProfileData {
  formation: string;
  specialite: string;
  niveau: string;
  telephone?: string;
  adresse?: string;
  ville?: string;
  bio?: string;
  linkedin?: string;
  github?: string;
  experiences?: StudentExperience[];
  projects?: StudentProject[];
  languages?: StudentLanguage[];
}

export interface StudentExperience {
  poste: string;
  entreprise: string;
  lieu: string;
  date_debut: string;
  date_fin: string;
  description: string;
}

export interface StudentProject {
  nom: string;
  technologies: string;
  lien: string;
  description: string;
}

export interface StudentLanguage {
  nom: string;
  niveau: 'Débutant' | 'Intermédiaire' | 'Avancé' | 'Bilingue';
}

interface CompanyProfileData {
  nom_entreprise: string;
  secteur_activite: string;
  adresse: string;
  description: string;
  ville?: string;
  telephone?: string;
  site_web?: string;
  linkedin?: string;
}

type ProfileData = StudentProfileData | CompanyProfileData;

export interface ProfileResponse {
  success: boolean;
  message: string;
  profile?: Record<string, any>;
  photo_url?: string;
  completion_percentage?: number;
  missing_items?: string[];
  errors?: any;
}

@Injectable({
  providedIn: 'root'
})
export class ProfileService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  private get token(): string | null {
    return localStorage.getItem('auth_token');
  }

  createProfile(data: ProfileData): Observable<ProfileResponse> {
    return this.http.post<ProfileResponse>(`${this.apiUrl}/profile`, data, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  getProfile(): Observable<ProfileResponse> {
    return this.http.get<ProfileResponse>(`${this.apiUrl}/profile`, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  updateProfile(data: ProfileData): Observable<ProfileResponse> {
    return this.http.put<ProfileResponse>(`${this.apiUrl}/profile`, data, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  uploadPhoto(file: File): Observable<ProfileResponse> {
    const formData = new FormData();
    formData.append('photo', file);

    return this.http.post<ProfileResponse>(`${this.apiUrl}/profile/photo`, formData, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }
}
