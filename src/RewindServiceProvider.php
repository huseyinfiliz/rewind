<?php

namespace HuseyinFiliz\Rewind;

use Flarum\Foundation\AbstractServiceProvider;
use HuseyinFiliz\Rewind\Community\CommunityMetricRegistry;
use HuseyinFiliz\Rewind\Community\Metric as CommunityMetrics;
use HuseyinFiliz\Rewind\Metric\Core;
use HuseyinFiliz\Rewind\Metric\MetricRegistry;
use HuseyinFiliz\Rewind\Template\RewindTemplateManager;
use HuseyinFiliz\Rewind\View\RewindViewResolver;

class RewindServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ContentVisibility::class);
        $this->container->singleton(RewindViewResolver::class);
        $this->container->singleton(RewindTemplateManager::class);

        $this->container->singleton(MetricRegistry::class);

        $this->container->resolving(MetricRegistry::class, function (MetricRegistry $registry) {
            $registry->addMetric($this->container->make(Core\PostCount::class));
            $registry->addMetric($this->container->make(Core\DiscussionCount::class));
            $registry->addMetric($this->container->make(Core\ActiveDays::class));
            $registry->addMetric($this->container->make(Core\TopTag::class));
            $registry->addMetric($this->container->make(Core\MostActiveMonth::class));
            $registry->addMetric($this->container->make(Core\WordCount::class));
            $registry->addMetric($this->container->make(Core\BestPost::class));
            $registry->addMetric($this->container->make(Core\TopWords::class));
            $registry->addMetric($this->container->make(Core\TopEmojis::class));
            $registry->addMetric($this->container->make(Core\NightOwl::class));
        });

        $this->container->singleton(CommunityMetricRegistry::class);

        $this->container->resolving(CommunityMetricRegistry::class, function (CommunityMetricRegistry $registry) {
            // Core
            $registry->addMetric(new CommunityMetrics\NewUsers());
            $registry->addMetric($this->container->make(CommunityMetrics\TotalPosts::class));
            $registry->addMetric($this->container->make(CommunityMetrics\TotalDiscussions::class));
            $registry->addMetric($this->container->make(CommunityMetrics\ActiveMembers::class));
            $registry->addMetric($this->container->make(CommunityMetrics\TopDiscussion::class));
            $registry->addMetric($this->container->make(CommunityMetrics\MostActiveUser::class));
            $registry->addMetric($this->container->make(CommunityMetrics\TotalWords::class));
            $registry->addMetric(new CommunityMetrics\NewMembersList());
            $registry->addMetric($this->container->make(CommunityMetrics\BusiestMonth::class));
            $registry->addMetric($this->container->make(CommunityMetrics\PeakHour::class));
            $registry->addMetric($this->container->make(CommunityMetrics\TopContributors::class));

            // Optional: tags
            $registry->addMetric($this->container->make(CommunityMetrics\CommunityTopTag::class));

            // Optional: likes
            $registry->addMetric($this->container->make(CommunityMetrics\StarPost::class));
            $registry->addMetric($this->container->make(CommunityMetrics\MostLoved::class));

            // Optional: other extensions
            $registry->addMetric($this->container->make(CommunityMetrics\TotalBestAnswers::class));
            $registry->addMetric($this->container->make(CommunityMetrics\BestAnswersLeaderboard::class));
            $registry->addMetric($this->container->make(CommunityMetrics\TotalReactions::class));
            $registry->addMetric($this->container->make(CommunityMetrics\TotalBadges::class));
            $registry->addMetric($this->container->make(CommunityMetrics\BadgeLeaderboard::class));
        });
    }
}
