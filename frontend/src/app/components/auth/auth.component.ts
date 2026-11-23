import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-auth',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './auth.component.html',
  styleUrl: './auth.component.css'
})
export class AuthComponent implements OnInit {
  isLoginMode = true;
  isLoading = false;
  errorMessage = '';

  // Login form
  loginData = {
    email: '',
    password: ''
  };

  // Register form
  registerData = {
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'etudiant' as 'etudiant' | 'entreprise'
  };

  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    if (this.authService.isAuthenticated()) {
      this.router.navigate(['/dashboard'], { replaceUrl: true });
    }
  }

  toggleMode(): void {
    this.isLoginMode = !this.isLoginMode;
    this.errorMessage = '';
    this.resetForms();
  }

  resetForms(): void {
    this.loginData = { email: '', password: '' };
    this.registerData = {
      first_name: '',
      last_name: '',
      email: '',
      password: '',
      password_confirmation: '',
      role: 'etudiant'
    };
  }

  onLogin(): void {
    this.isLoading = true;
    this.errorMessage = '';

    this.authService.login(this.loginData).subscribe({
      next: (response) => {
        console.log('Login response:', response);
        if (response.success && response.token) {
          this.authService.setToken(response.token);
          this.authService.setUser(response.user);
          this.router.navigate(['/dashboard']);
        } else {
          console.log('Login failed:', response);
          this.errorMessage = response.message || 'Erreur lors de la connexion';
        }
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Login error:', error);
        
        // Erreur réseau ou serveur inaccessible
        if (error.status === 0 || error.name === 'HttpErrorResponse') {
          this.errorMessage = 'Serveur inaccessible. Vérifiez que le backend Laravel est démarré sur le port 8000.';
        }
        // Identifiants incorrects (401)
        else if (error.status === 401) {
          this.errorMessage = error.error?.message || 'Email ou mot de passe incorrect';
        }
        // Erreur de validation (422)
        else if (error.status === 422) {
          this.errorMessage = error.error?.message || 'Données invalides';
        }
        // Autre erreur
        else {
          this.errorMessage = error.error?.message || `Erreur lors de la connexion (${error.status || 'réseau'})`;
        }
        
        this.isLoading = false;
      }
    });
  }

  onRegister(): void {
    this.isLoading = true;
    this.errorMessage = '';

    if (this.registerData.password !== this.registerData.password_confirmation) {
      this.errorMessage = 'Les mots de passe ne correspondent pas';
      this.isLoading = false;
      return;
    }

    this.authService.register(this.registerData).subscribe({
      next: (response) => {
        if (response.success && response.token) {
          this.authService.setToken(response.token);
          this.authService.setUser(response.user);
          this.router.navigate(['/dashboard']);
        } else {
          this.errorMessage = response.message || 'Erreur lors de l\'inscription';
        }
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Register error:', error);
        
        // Erreur réseau ou serveur inaccessible
        if (error.status === 0 || error.name === 'HttpErrorResponse') {
          this.errorMessage = 'Serveur inaccessible. Vérifiez que le backend Laravel est démarré sur le port 8000.';
        }
        // Erreur de validation (422)
        else if (error.status === 422) {
          if (error.error?.errors) {
            const errors = Object.values(error.error.errors).flat();
            this.errorMessage = errors.join(', ');
          } else {
            this.errorMessage = error.error?.message || 'Données invalides';
          }
        }
        // Autre erreur
        else {
          this.errorMessage = error.error?.message || `Erreur lors de l'inscription (${error.status || 'réseau'})`;
        }
        
        this.isLoading = false;
      }
    });
  }
}
