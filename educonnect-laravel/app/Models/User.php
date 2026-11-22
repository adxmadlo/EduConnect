<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Utilisateur EduConnect — un seul compte porte le rôle métier.
 * Rôles identiques à l'ancien ENUM users.role.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['etudiant', 'parent', 'enseignant', 'mentor', 'admin', 'chef_admin'];

    /** Libellés français pour l'interface. */
    public const LIBELLES_ROLES = [
        'etudiant'   => 'Étudiant',
        'parent'     => 'Parent',
        'enseignant' => 'Enseignant',
        'mentor'     => 'Mentor',
        'admin'      => 'Administrateur',
        'chef_admin' => 'Chef administrateur',
    ];

    protected $fillable = [
        'numero_ucao', 'nom', 'prenom', 'email', 'telephone', 'password',
        'role', 'statut', 'photo_url', 'dernier_login',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'dernier_login' => 'datetime',
        'password'      => 'hashed',
    ];

    public function nomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function initiales(): string
    {
        return mb_strtoupper(mb_substr($this->prenom, 0, 1) . mb_substr($this->nom, 0, 1));
    }

    public function libelleRole(): string
    {
        return self::LIBELLES_ROLES[$this->role] ?? $this->role;
    }

    public function estAdmin(): bool
    {
        return in_array($this->role, ['admin', 'chef_admin'], true);
    }

    public function etudiant()
    {
        return $this->hasOne(Etudiant::class);
    }

    public function enseignant()
    {
        return $this->hasOne(Enseignant::class);
    }

    public function mentor()
    {
        return $this->hasOne(Mentor::class);
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function notificationsEduconnect()
    {
        return $this->hasMany(Notification::class);
    }
}
