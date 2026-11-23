import { Component, Input, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-progress-circle',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './progress-circle.component.html',
  styleUrl: './progress-circle.component.css'
})
export class ProgressCircleComponent implements OnInit {
  @Input() value = 0;
  @Input() size = 120;
  @Input() strokeWidth = 8;
  @Input() color: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' = 'primary';
  @Input() showLabel = true;
  @Input() label = '';

  circumference = 0;
  offset = 0;

  ngOnInit() {
    this.circumference = 2 * Math.PI * (this.size / 2 - this.strokeWidth);
    this.offset = this.circumference - (this.value / 100) * this.circumference;
  }

  get strokeColor(): string {
    const colors = {
      primary: 'var(--color-primary)',
      secondary: 'var(--color-secondary)',
      success: 'var(--color-success)',
      warning: 'var(--color-warning)',
      danger: 'var(--color-danger)'
    };
    return colors[this.color];
  }

  get trackColor(): string {
    return 'var(--color-bg-tertiary)';
  }

  get textColor(): string {
    const colors = {
      primary: 'var(--color-primary-dark)',
      secondary: 'var(--color-secondary-hover)',
      success: 'var(--color-success)',
      warning: 'var(--color-warning)',
      danger: 'var(--color-danger)'
    };
    return colors[this.color];
  }

  get scoreLabel(): string {
    if (this.value >= 80) return 'Excellent';
    if (this.value >= 60) return 'Bon';
    if (this.value >= 40) return 'Moyen';
    return 'À améliorer';
  }
}
