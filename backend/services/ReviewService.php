<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../dao/ReviewDao.php';

final class ReviewService extends BaseService
{
    public function __construct(PDO $pdo)
    {
        parent::__construct(new ReviewDao($pdo));
    }

    protected function authorizeList(?array $actor): void
    {
    }

    protected function authorizeCreate(?array $actor): void
    {
        $this->requireActor($actor);
    }

    protected function authorizeUpdate(array $row, ?array $actor): void
    {
        $this->requireOwnerOrAdmin($row, $actor);
    }

    protected function authorizeDelete(array $row, ?array $actor): void
    {
        $this->requireOwnerOrAdmin($row, $actor);
    }

    protected function canRead(array $row, ?array $actor): bool
    {
        return true;
    }

    protected function normalizeCreate(array $input, ?array $actor): array
    {
        $actor = $this->requireActor($actor);
        $this->rejectUnknownFields($input, ['user_id', 'product_id', 'rating', 'comment']);
        $userId = $actor['role'] === 'admin'
            ? $this->positiveInteger($input, 'user_id')
            : $actor['id'];

        return [
            'user_id' => $userId,
            'product_id' => $this->positiveInteger($input, 'product_id'),
            'rating' => $this->rating($input),
            'comment' => $this->comment($input),
        ];
    }

    protected function normalizeUpdate(array $input, ?array $actor, array $row): array
    {
        $this->rejectUnknownFields($input, ['rating', 'comment']);
        $data = [];
        if (array_key_exists('rating', $input)) {
            $data['rating'] = $this->rating($input);
        }
        if (array_key_exists('comment', $input)) {
            $data['comment'] = $this->comment($input);
        }

        return $data;
    }

    private function rating(array $input): int
    {
        $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
        if ($rating === false || $rating < 1 || $rating > 5) {
            throw new ApiException('Rating must be an integer from 1 to 5.', 400);
        }

        return $rating;
    }

    private function comment(array $input): ?string
    {
        if (!array_key_exists('comment', $input) || $input['comment'] === null) {
            return null;
        }
        if (!is_string($input['comment']) || strlen($input['comment']) > 1000) {
            throw new ApiException('Comment must be a string of at most 1000 characters.', 400);
        }

        return trim($input['comment']);
    }

    private function requireOwnerOrAdmin(array $row, ?array $actor): void
    {
        $actor = $this->requireActor($actor);
        if ($actor['role'] !== 'admin' && (int) $actor['id'] !== (int) $row['user_id']) {
            throw new ApiException('You can only modify your own reviews.', 403);
        }
    }
}
