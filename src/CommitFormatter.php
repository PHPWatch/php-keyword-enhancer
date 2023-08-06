<?php

namespace PHPWatch\PHPCommitBuilder;

class CommitFormatter {
    private array $commitsList = [];

    private array $commitsGroupedByAuthor = [];
    private const EOL = "\r\n";

    public function __construct(array $inputCommits) {
        $this->process($inputCommits);
    }

    private function process(array $inputCommits): void {
        $formattedCommits = [];
        $i = 0;

        foreach ($inputCommits as $commit) {
            $commitArray = $this->splitCommit($commit);
            if ($this->shouldSkip($commitArray['subject'])) {
                continue;
            }

            $commitArray['formatted'] = KeywordEnhancer::enhanceCommit($commitArray['subject'], substr($commitArray['hash'], 0, 10));
            $formattedCommits[$i] = $commitArray;

            if (!isset($this->commitsGroupedByAuthor[$commitArray['author']])) {
                $this->commitsGroupedByAuthor[$commitArray['author']] = [];
            }

            $this->commitsGroupedByAuthor[$commitArray['author']][] = $i;

            $i++;
        }

        $this->commitsList = $formattedCommits;
        ksort($this->commitsGroupedByAuthor);
    }

    private function splitCommit(\stdClass $commit): array {
        $commitMessage = $commit->commit->message;
        $commitMessageParts = explode("\n", $commitMessage, 2);

        return [
            'subject' => trim(trim($commitMessageParts[0]), '.'),
            'author' => trim($commit->commit->author->name),
            'hash' => trim($commit->sha),
            'message' => trim($commitMessage)
        ];
    }

    private function shouldSkip(string $commitMessage): bool {
        if ($commitMessage === '') {
            return true;
        }

        // Skip merge commits
        if (str_starts_with($commitMessage, 'Merge branch')) {
            return true;
        }

        // Skip merge commits
        if (str_starts_with($commitMessage, 'Merge remote-tracking branch')) {
            return true;
        }

        // Skip "[ci skip]" messages
        if (str_contains($commitMessage, '[ci skip]') || str_contains($commitMessage, '[skip ci]')) {
            return true;
        }

        if (str_contains($commitMessage, 'is now for PHP 8') || str_contains($commitMessage, 'is now for PHP-8')) {
            return true;
        }

        if (str_starts_with($commitMessage, 'Update NEWS for ')) {
            return true;
        }

        return false;
    }

    public function getFormattedCommitList(): array {
        return $this->commitsList;
    }

    public function getFormattedCommitListMarkup(): string {
        $output = '';
        foreach ($this->getFormattedCommitList() as $commit) {
            $output .= $this->getFormattedCommitListMarkup($commit['formatted'] . ' by ' . $commit['author']);
        }

        return $output;
    }

    public function getFormattedCommitListGroupedByAuthor(): array {
        $return = $this->commitsGroupedByAuthor;
        foreach ($return as $author => &$commitList) {
            foreach ($commitList as $key => &$commitI) {
                $commitI = $this->commitsList[$commitI];
            }
        }

        return $return;
    }

    public function getFormattedCommitListGroupedByAuthorMarkup(): string {
        $commitsByAuthor = $this->getFormattedCommitListGroupedByAuthor();

        $output = '';
        foreach ($commitsByAuthor as $author => $commitList) {
            $output .= self::markdownTitle($author);
            foreach ($commitList as $commit) {
                $output .= self::markdownListItem($commit['formatted']);
            }
        }

        return $output;
    }

    private static function markdownTitle(string $title): string {
        return '### ' . self::plainText($title) . static::EOL;
    }

    private static function markdownListItem(string $listItem): string {
        return ' - ' . self::plainText($listItem) . static::EOL;
    }

    private static function plainText(string $text): string {
        return htmlspecialchars($text);
    }
}
