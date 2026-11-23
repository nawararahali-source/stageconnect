<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternshipOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'domain',
        'description',
        'duration',
        'location',
        'work_mode',
        'required_skills',
        'positions',
        'deadline',
        'status',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'positions' => 'integer',
        'deadline' => 'date:Y-m-d',
    ];

    protected $appends = [
        'available_positions',
    ];

    public function scopeWithAcceptedApplicationsCount(Builder $query): Builder
    {
        return $query->withCount([
            'applications as accepted_applications_count' => function ($query) {
                $query->where('status', 'accepted');
            },
        ]);
    }

    public function getAvailablePositionsAttribute(): int
    {
        $acceptedCount = $this->attributes['accepted_applications_count']
            ?? $this->applications()->where('status', 'accepted')->count();

        return max(0, $this->positions - (int) $acceptedCount);
    }

    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    /**
     * Get the applications for this offer.
     */
    public function applications()
    {
        return $this->hasMany(Application::class, 'internship_offer_id');
    }
}
