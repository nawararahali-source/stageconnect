<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'formation',
        'specialite',
        'niveau',
        'nom_entreprise',
        'secteur_activite',
        'adresse',
        'description',
        'telephone',
        'ville',
        'bio',
        'linkedin',
        'github',
        'site_web',
        'photo_path',
        'experiences',
        'projects',
        'languages',
    ];

    protected $casts = [
        'experiences' => 'array',
        'projects' => 'array',
        'languages' => 'array',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
