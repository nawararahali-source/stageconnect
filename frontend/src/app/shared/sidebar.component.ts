import { Component, Input, Output, EventEmitter, HostListener, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { EMPTY, Subscription, timer } from 'rxjs';
import { catchError, switchMap } from 'rxjs/operators';
import { MessageService } from '../services/message.service';

export interface SidebarItem {
  label: string;
  icon: string;
  link: string;
  fragment?: string;
  badge?: number | string;
  role?: 'student' | 'company' | 'both';
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './sidebar.component.html',
  styleUrl: './sidebar.component.css'
})
export class SidebarComponent implements OnInit, OnDestroy {
  @Input() isCollapsed = false;
  @Input() userRole: 'student' | 'company' = 'student';
  @Input() userName = '';
  @Input() userAvatar = '';
  @Output() toggleCollapse = new EventEmitter<void>();
  @Output() logout = new EventEmitter<void>();

  isMobile = false;
  private unreadCountSubscription?: Subscription;

  sidebarItems: SidebarItem[] = [
    { label: 'Tableau de bord', icon: 'home', link: '/dashboard', role: 'both' },
    { label: 'Mon profil', icon: 'user', link: '/profile', role: 'both' },
    { label: 'Messages', icon: 'message', link: '/messages', badge: 0, role: 'both' },
    { label: 'Compétences', icon: 'zap', link: '/profile', fragment: 'skills', role: 'student' },
    { label: 'Parcours & projets', icon: 'briefcase', link: '/profile', fragment: 'portfolio', role: 'student' },
    { label: 'Mon CV', icon: 'file-text', link: '/profile', fragment: 'cv', role: 'student' },
    { label: 'Offres de stage', icon: 'briefcase', link: '/dashboard', fragment: 'student-offers', role: 'student' },
    { label: 'Mes candidatures', icon: 'send', link: '/dashboard', fragment: 'student-applications', role: 'student' },
    { label: 'Mes offres', icon: 'briefcase', link: '/dashboard', fragment: 'offers', role: 'company' },
    { label: 'Candidatures reçues', icon: 'users', link: '/dashboard', fragment: 'company-applications', role: 'company' },
  ];

  constructor(private messageService: MessageService) {}

  @HostListener('window:resize', ['$event'])
  onResize(event: Event) {
    this.checkMobile();
  }

  ngOnInit() {
    this.checkMobile();
    this.unreadCountSubscription = timer(0, 5000).pipe(
      switchMap(() => this.messageService.getUnreadCount().pipe(
        catchError((error) => {
          console.error('Impossible de charger le nombre de messages non lus.', error);
          return EMPTY;
        })
      ))
    ).subscribe((response) => {
      const messagesItem = this.sidebarItems.find((item) => item.icon === 'message');
      if (messagesItem) {
        messagesItem.badge = response.unread_count;
      }
    });
  }

  ngOnDestroy(): void {
    this.unreadCountSubscription?.unsubscribe();
  }

  checkMobile() {
    this.isMobile = window.innerWidth < 1024;
  }

  get filteredItems(): SidebarItem[] {
    return this.sidebarItems.filter(
      item => item.role === 'both' || item.role === this.userRole
    );
  }

  get initials(): string {
    return this.userName
      .split(' ')
      .map(n => n.charAt(0))
      .join('')
      .toUpperCase()
      .slice(0, 2);
  }

  onToggle() {
    this.toggleCollapse.emit();
  }

  onItemClick(): void {
    if (this.isMobile && !this.isCollapsed) {
      this.onToggle();
    }
  }

  onLogout() {
    this.logout.emit();
  }
}
