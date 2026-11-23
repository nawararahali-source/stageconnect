import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-stat-card',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './stat-card.component.html',
  styleUrl: './stat-card.component.css'
})
export class StatCardComponent {
  @Input() label = '';
  @Input() value: number | string = 0;
  @Input() icon = '';
  @Input() trend?: 'up' | 'down' | 'neutral';
  @Input() trendValue?: number;
  @Input() color: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' = 'primary';
  @Input() loading = false;

  get trendIcon(): string {
    switch (this.trend) {
      case 'up': return '↑';
      case 'down': return '↓';
      default: return '→';
    }
  }

  get trendColor(): string {
    switch (this.trend) {
      case 'up': return 'var(--color-success)';
      case 'down': return 'var(--color-danger)';
      default: return 'var(--color-text-tertiary)';
    }
  }

  get cardColor(): string {
    const colors = {
      primary: 'var(--color-primary)',
      secondary: 'var(--color-secondary)',
      success: 'var(--color-success)',
      warning: 'var(--color-warning)',
      danger: 'var(--color-danger)'
    };
    return colors[this.color];
  }

  get cardLightColor(): string {
    const colors = {
      primary: 'var(--color-primary-light)',
      secondary: 'var(--color-secondary-light)',
      success: 'var(--color-success-light)',
      warning: 'var(--color-warning-light)',
      danger: 'var(--color-danger-light)'
    };
    return colors[this.color];
  }
}
