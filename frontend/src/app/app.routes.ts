import { Routes } from '@angular/router';
import { AuthComponent } from './components/auth/auth.component';
import { WelcomeComponent } from './components/welcome/welcome.component';
import { ProfileComponent } from './profile/profile.component';
import { DashboardComponent } from './dashboard/dashboard.component';
import { MessagesComponent } from './messages/messages.component';

export const routes: Routes = [
  { path: '', component: AuthComponent },
  { path: 'welcome', component: WelcomeComponent },
  { path: 'dashboard', component: DashboardComponent },
  { path: 'messages', component: MessagesComponent },
  { path: 'profile', component: ProfileComponent },
  { path: '**', redirectTo: '' }
];
