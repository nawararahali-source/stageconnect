<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'internship_offer_id',
        'status',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    /**
     * Get the student who applied.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the internship offer.
     */
    public function internshipOffer()
    {
        return $this->belongsTo(InternshipOffer::class, 'internship_offer_id');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }
}
