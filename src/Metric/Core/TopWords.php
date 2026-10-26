<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class TopWords implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    private const STOP_WORDS = [
        // English
        'this' => 1, 'that' => 1, 'with' => 1, 'from' => 1, 'have' => 1, 'been' => 1,
        'were' => 1, 'they' => 1, 'them' => 1, 'their' => 1, 'there' => 1, 'here' => 1,
        'what' => 1, 'when' => 1, 'where' => 1, 'which' => 1, 'while' => 1, 'will' => 1,
        'would' => 1, 'could' => 1, 'should' => 1, 'about' => 1, 'after' => 1, 'before' => 1,
        'being' => 1, 'between' => 1, 'does' => 1, 'done' => 1, 'each' => 1, 'even' => 1,
        'every' => 1, 'first' => 1, 'also' => 1, 'just' => 1, 'like' => 1, 'make' => 1,
        'made' => 1, 'many' => 1, 'more' => 1, 'most' => 1, 'much' => 1, 'must' => 1,
        'need' => 1, 'only' => 1, 'other' => 1, 'over' => 1, 'same' => 1, 'some' => 1,
        'such' => 1, 'take' => 1, 'than' => 1, 'then' => 1, 'these' => 1, 'those' => 1,
        'very' => 1, 'well' => 1, 'your' => 1, 'into' => 1, 'know' => 1, 'think' => 1,
        'want' => 1, 'going' => 1, 'thing' => 1, 'things' => 1, 'really' => 1, 'still' => 1,
        'good' => 1, 'because' => 1, 'though' => 1, 'through' => 1, 'since' => 1,
        // Turkish
        'bile' => 1, 'bunu' => 1, 'daha' => 1, 'değil' => 1, 'gibi' => 1, 'için' => 1,
        'kadar' => 1, 'nasıl' => 1, 'neden' => 1, 'olan' => 1, 'olarak' => 1, 'oluyor' => 1,
        'sadece' => 1, 'sonra' => 1, 'zaten' => 1, 'ancak' => 1, 'fakat' => 1, 'yani' => 1,
        'evet' => 1, 'şey' => 1, 'şeyi' => 1, 'şeyin' => 1, 'böyle' => 1, 'şöyle' => 1,
        'artık' => 1, 'birçok' => 1, 'diğer' => 1, 'hangi' => 1, 'nerede' => 1, 'burada' => 1,
        'orada' => 1, 'aynı' => 1, 'başka' => 1, 'bence' => 1, 'sence' => 1,
        // URL/tech leftovers
        'https' => 1, 'http' => 1, 'www' => 1, 'com' => 1, 'org' => 1, 'net' => 1,
        'html' => 1, 'quot' => 1, 'amp' => 1, 'nbsp' => 1,
        // Forum/tech common words
        'continue' => 1, 'reply' => 1, 'quote' => 1, 'edit' => 1, 'delete' => 1,
        'post' => 1, 'topic' => 1, 'thread' => 1, 'forum' => 1, 'user' => 1,
        'link' => 1, 'image' => 1, 'file' => 1, 'code' => 1, 'text' => 1,
        'page' => 1, 'site' => 1, 'click' => 1, 'button' => 1,
        'youtube' => 1, 'twitter' => 1, 'facebook' => 1, 'instagram' => 1, 'tiktok' => 1,
    ];

    public function key(): string
    {
        return 'top_words';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $wordCounts = [];

        $this->visibility->posts()->where('user_id', $user->id)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('id', 'content')
            ->chunkById(500, function ($posts) use (&$wordCounts) {
                foreach ($posts as $post) {
                    $text = \HuseyinFiliz\Rewind\ContentCleaner::toPlainText($post->content ?? '');
                    $text = mb_strtolower($text);
                    // Keep only letters (no numbers)
                    $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
                    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($words as $word) {
                        if (mb_strlen($word) < 4) {
                            continue;
                        }
                        // Filter out numbers just in case
                        if (is_numeric($word)) {
                            continue;
                        }
                        if (isset(self::STOP_WORDS[$word])) {
                            continue;
                        }

                        $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                    }
                }
            });

        arsort($wordCounts);

        $topWords = array_slice($wordCounts, 0, 8, true);

        $words = [];
        foreach ($topWords as $word => $count) {
            $words[] = ['word' => $word, 'count' => $count];
        }

        return ['words' => $words];
    }
}
