<?php

namespace HuseyinFiliz\Rewind\Metric\Core;

use Flarum\User\User;
use HuseyinFiliz\Rewind\ContentVisibility;
use HuseyinFiliz\Rewind\Metric\RewindMetric;

class TopEmojis implements RewindMetric
{
    public function __construct(protected ContentVisibility $visibility)
    {
    }

    public function key(): string
    {
        return 'top_emojis';
    }

    public function requiredExtension(): ?string
    {
        return null;
    }

    public function calculate(User $user, int $year): array
    {
        $emojiCounts = [];

        // Base emoji: visible emoji codepoints (covers through Emoji 15.0)
        $base = '(?:'
            .'[\x{1F600}-\x{1F64F}]'   // Emoticons
            .'|[\x{1F300}-\x{1F5FF}]'  // Misc Symbols & Pictographs
            .'|[\x{1F680}-\x{1F6FF}]'  // Transport & Map
            .'|[\x{1F900}-\x{1F9FF}]'  // Supplemental Symbols & Pictographs
            .'|[\x{1FA00}-\x{1FA6F}]'  // Chess Symbols, Symbols Extended-A
            .'|[\x{1FA70}-\x{1FAFF}]'  // Symbols Extended-A (rest)
            .'|[\x{2600}-\x{26FF}]'    // Misc Symbols
            .'|[\x{2700}-\x{27BF}]'    // Dingbats
            .'|[\x{231A}-\x{231B}]'    // Watch, Hourglass
            .'|[\x{23E9}-\x{23F3}]'    // Media control symbols
            .'|[\x{23F8}-\x{23FA}]'    // Media control symbols
            .'|[\x{25AA}-\x{25AB}]'    // Small squares
            .'|\x{25B6}|\x{25C0}'      // Play buttons
            .'|[\x{25FB}-\x{25FE}]'    // Medium squares
            .'|[\x{2934}-\x{2935}]'    // Curved arrows
            .'|[\x{2B05}-\x{2B07}]'    // Arrows
            .'|[\x{2B1B}-\x{2B1C}]'    // Large squares
            .'|\x{2B50}|\x{2B55}'      // Star, circle
            .'|\x{3030}|\x{303D}'      // Wavy dash
            .'|\x{3297}|\x{3299}'      // CJK ideographs
            .'|\x{00A9}|\x{00AE}'      // ©®
            .')';

        // Modifiers that follow a base emoji
        $mod = '[\x{1F3FB}-\x{1F3FF}\x{FE0E}\x{FE0F}]';

        // Regional indicator flag pair (e.g. 🇺🇸)
        $flag = '[\x{1F1E0}-\x{1F1FF}]{2}';

        // Keycap sequence (e.g. #️⃣, 0️⃣-9️⃣)
        $keycap = '[0-9#*]\x{FE0F}?\x{20E3}';

        // Complete emoji: flag | keycap | base with optional modifiers and ZWJ chains
        $emojiPattern = '/(?:'.$flag.'|'.$keycap.'|'.$base.$mod.'*(?:\x{200D}'.$base.$mod.'*)*)/u';

        $this->visibility->posts()->where('user_id', $user->id)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->where('created_at', '>=', "$year-01-01 00:00:00")
            ->where('created_at', '<', ($year + 1).'-01-01 00:00:00')
            ->select('id', 'content')
            ->chunkById(500, function ($posts) use (&$emojiCounts, $emojiPattern) {
                foreach ($posts as $post) {
                    // Content is stored as TextFormatter XML with HTML entities
                    // (e.g. &#128104;‍✈️). Decode entities first so the regex
                    // can match complete ZWJ sequences as Unicode.
                    $text = html_entity_decode(strip_tags($post->content ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if (preg_match_all($emojiPattern, $text, $matches)) {
                        foreach ($matches[0] as $emoji) {
                            $emojiCounts[$emoji] = ($emojiCounts[$emoji] ?? 0) + 1;
                        }
                    }
                }
            });

        arsort($emojiCounts);

        $topEmojis = array_slice($emojiCounts, 0, 5, true);

        $emojis = [];
        foreach ($topEmojis as $emoji => $count) {
            $emojis[] = ['emoji' => $emoji, 'count' => $count];
        }

        return [
            'total' => array_sum($emojiCounts),
            'emojis' => $emojis,
        ];
    }
}
