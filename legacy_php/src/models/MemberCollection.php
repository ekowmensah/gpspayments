<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Member Collection assignment model.
 */
class MemberCollection extends BaseModel {
    protected string $table = 'member_collections';
    protected array $fillable = [
        'member_id',
        'collection_item_id',
        'assignment_date',
        'status',
    ];
}
?>

