<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A free-form catalog tag. `group` names the filter group the value belongs
 * to; today only `category` is stored here, since department comes off the
 * tool itself and status is derived.
 *
 * @property int $id
 * @property string $group
 * @property string $value
 * @property-read Collection<int, Tool> $tools
 */
#[Fillable(['group', 'value'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    public const GROUP_CATEGORY = 'category';

    /**
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }
}
