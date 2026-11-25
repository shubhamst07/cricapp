<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticleTranslationService;
use Illuminate\Console\Command;

class FixArticleTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:fix-translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all published articles and generate authentic Hindi and Hinglish translations for any article missing them';

    /**
     * Execute the console command.
     */
    public function handle(ArticleTranslationService $translationService): int
    {
        $this->info("Scanning articles for missing or invalid translations...");

        $articles = Article::all();
        $fixed = 0;

        foreach ($articles as $article) {
            $hasHi = $translationService->isDevanagari($article->title_hi) 
                && $translationService->isDevanagari($article->content_hi);

            if (!$hasHi) {
                $this->line("Translating article: [{$article->id}] {$article->title}...");
                try {
                    $translationService->getTranslations($article);
                    $fixed++;
                } catch (\Throwable $e) {
                    $this->warn("Failed to translate article {$article->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Completed. Successfully translated {$fixed} articles.");
        return Command::SUCCESS;
    }
}
