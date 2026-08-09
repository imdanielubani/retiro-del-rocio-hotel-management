<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A category on the Kitchen or Bar & Lounge menu (e.g. "Mains", "Cocktails").
 * MenuItem.category stores the matching slug as a plain string rather than a
 * foreign key, so existing rows never needed a data migration when this
 * table was introduced — see the create_menu_categories_table migration.
 */
class MenuCategory extends Model
{
    protected $fillable = ['name', 'slug', 'department', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public const DEPARTMENTS = ['food', 'drink'];

    public function scopeFood($query)
    {
        return $query->where('department', 'food');
    }

    public function scopeDrink($query)
    {
        return $query->where('department', 'drink');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** How many menu items currently use this category — guards deletion. */
    public function itemCount(): int
    {
        return MenuItem::where('category', $this->slug)->count();
    }
}
