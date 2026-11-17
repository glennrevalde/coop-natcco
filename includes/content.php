<?php
/**
 * Shared labels for public content types.
 */
declare(strict_types=1);

function legislative_status_options(): array
{
    return ['filed' => 'Filed', 'pending_committee' => 'Pending in committee', 'committee_approved' => 'Approved by committee', 'second_reading' => 'Approved on second reading',
        'third_reading' => 'Approved on third reading', 'bicameral' => 'Bicameral conference', 'enrolled' => 'Enrolled / for signature', 'enacted' => 'Enacted into law',
        'vetoed' => 'Vetoed', 'adopted' => 'Adopted', 'archived' => 'Archived (lapsed)', 'other' => 'Other'];
}

function measure_type_options(): array
{
    return ['house_bill' => 'House Bill', 'senate_bill' => 'Senate Bill', 'house_resolution' => 'House Resolution', 'senate_resolution' => 'Senate Resolution',
        'joint_resolution' => 'Joint Resolution', 'republic_act' => 'Republic Act', 'executive_issuance' => 'Executive issuance', 'other' => 'Other measure'];
}

function post_type_labels(bool $plural = false): array
{
    return $plural
        ? ['news' => 'News', 'announcement' => 'Announcements', 'achievement' => 'Achievements', 'newsletter' => 'Newsletters', 'program' => 'Programs', 'activity' => 'Activities', 'publication' => 'Publications']
        : ['news' => 'News', 'announcement' => 'Announcement', 'achievement' => 'Achievement', 'newsletter' => 'Newsletter', 'program' => 'Program', 'activity' => 'Activity', 'publication' => 'Publication'];
}

function doc_type_options(): array
{
    return ['policy' => 'Policy', 'procedure' => 'Procedure', 'guideline' => 'Administrative guideline', 'circular' => 'Circular', 'memorandum' => 'Memorandum', 'manual' => 'Operational manual', 'form' => 'Official form'];
}

/** Can the visitor preview unpublished content? */
function can_preview(): bool
{
    return !empty($_GET['preview']) && current_user() && (can('content.view') || can('events.view'));
}
