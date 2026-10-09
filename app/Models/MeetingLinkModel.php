<?php

namespace App\Models;

use CodeIgniter\Model;

class MeetingLinkModel extends Model
{
    protected $table         = 'meeting_links';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'meeting_id',
        'link_type',
        'url',
    ];
    protected $useTimestamps = true;

    public function getByMeeting(int $meetingId): array
    {
        return $this->where('meeting_id', $meetingId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
