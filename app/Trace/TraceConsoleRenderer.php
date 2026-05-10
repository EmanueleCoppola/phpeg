<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Trace;

/**
 * Renders a trace document as ANSI terminal output.
 */
class TraceConsoleRenderer
{
    /**
     * Renders the full screen view for a given step.
     *
     * @param array<string, mixed> $document
     */
    public function render(array $document, int $stepIndex, bool $useColor = true, string $grammarView = 'source', string $mode = 'all'): string
    {
        $steps = $document['steps'] ?? [];
        $grammar = $document['grammar']['snapshot'] ?? [];
        $grammarSource = (string) ($document['grammar']['source'] ?? '');
        $input = (string) ($document['input']['source'] ?? '');
        $currentStep = $steps[$stepIndex] ?? null;
        $activePath = is_array($currentStep['path'] ?? null) ? $currentStep['path'] : [];
        $activeNodes = $this->resolveActiveNodes($grammar, $activePath);
        $activeRuleNames = $this->activeRuleNames($activeNodes);
        $focusRuleName = $this->focusRuleName($activeRuleNames);
        $focusStep = $this->focusTraceStep($steps, $stepIndex);

        $lines = [];
        $lines[] = $this->style($useColor, '1;36', 'PHPeg trace viewer');
        $lines[] = sprintf(
            'step %d / %d | mode=%s',
            $stepIndex,
            max(count($steps) - 1, 0),
            $mode,
        );
        $lines[] = '';
        $lines[] = $this->style($useColor, '1', 'Current step');
        $lines[] = $this->renderStepSummary($currentStep, $useColor);
        $lines[] = '';
        $lines[] = $this->style($useColor, '1', 'Grammar');
        if ($grammarView === 'tree') {
            $lines = array_merge($lines, $this->renderGrammarSnapshot($grammar, $activePath, $useColor));
        } else {
            $lines = array_merge($lines, $this->renderGrammarSource($grammarSource, $activeRuleNames, $focusStep, $focusRuleName, $useColor));
        }
        $lines[] = '';
        $lines[] = $this->style($useColor, '1', 'Input');
        $lines[] = $this->renderInput($input, $steps, $stepIndex, $useColor);
        $lines = array_merge($lines, $this->renderInputLegend($useColor));
        $lines[] = '';
        $lines[] = $this->style(
            $useColor,
            '2',
            'Commands: n/next, p/prev, g/goto <n>, a/all, m/matched, f/failures, q/quit',
        );

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * Renders a short step summary.
     *
     * @param array<string, mixed>|null $step
     */
    private function renderStepSummary(?array $step, bool $useColor): string
    {
        if ($step === null) {
            return $this->style($useColor, '31', 'No step selected.');
        }

        $status = $step['phase'] === 'exit'
            ? ($step['success'] ? 'match' : 'fail')
            : 'enter';
        $scope = (string) ($step['scope'] ?? 'scope');
        $label = (string) (($step['target']['label'] ?? $step['target']['name'] ?? $step['target']['description']) ?? 'step');
        $offset = (int) ($step['offset'] ?? 0);
        $endOffset = $step['endOffset'] ?? null;
        $silent = !empty($step['silent']);
        $path = is_array($step['path'] ?? null) ? implode(' > ', array_map('strval', $step['path'])) : '';

        $parts = [
            sprintf('%s %s', strtoupper($step['phase'] ?? 'step'), $label),
            sprintf('scope=%s', $scope),
            sprintf('offset=%d', $offset),
        ];

        if ($endOffset !== null) {
            $parts[] = sprintf('end=%d', (int) $endOffset);
        }

        $parts[] = sprintf('status=%s', $status);

        if ($silent) {
            $parts[] = 'silent';
        }

        if ($path !== '') {
            $parts[] = sprintf('path=%s', $path);
        }

        $message = (string) ($step['message'] ?? '');

        return implode(' | ', $parts) . PHP_EOL . $this->style($useColor, '37', $message);
    }

    /**
     * Renders the grammar snapshot.
     *
     * @param array<string, mixed> $grammar
     * @param list<int|string> $activePath
     * @return list<string>
     */
    private function renderGrammarSnapshot(array $grammar, array $activePath, bool $useColor): array
    {
        $lines = [];
        $lines[] = sprintf('start rule: %s', (string) ($grammar['startRule'] ?? ''));

        foreach ($grammar['rules'] ?? [] as $rule) {
            $lines = array_merge($lines, $this->renderNode($rule, $activePath, 0, $useColor, true));
        }

        if (($grammar['lakeProfiles'] ?? []) !== []) {
            $lines[] = 'lake profiles:';
            foreach ($grammar['lakeProfiles'] as $profile) {
                $lines[] = '  ' . $this->style($useColor, '36', '<' . (string) ($profile['name'] ?? '') . '>');
                $lines = array_merge($lines, $this->renderNode($profile['expression'] ?? [], $activePath, 2, $useColor, false));
            }
        }

        return $lines;
    }

    /**
     * Renders the original grammar source with lightweight highlighting.
     *
     * @param list<string> $activeRuleNames
     * @return list<string>
     */
    private function renderGrammarSource(string $source, array $activeRuleNames, ?array $focusStep, ?string $focusRuleName, bool $useColor): array
    {
        if ($source === '') {
            return ['(grammar source unavailable)'];
        }

        $activeColors = $this->activeRuleColors($activeRuleNames);
        $lines = [];
        if ($activeRuleNames !== []) {
            $lines[] = 'active path: ' . implode(' > ', array_map(
                fn (string $ruleName): string => $this->style($useColor, $activeColors[$ruleName] ?? $this->colorForKey($ruleName), $ruleName),
                $activeRuleNames,
            ));
        }

        $focusToken = $this->tokenForStep($focusStep);
        $grammarLines = preg_split("/\\R/", $source) ?: [];

        foreach ($grammarLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $lines[] = $line;

                continue;
            }

            if (preg_match('/^([ \t]*)([A-Za-z_][A-Za-z0-9_]*)([ \t]*)(=|<-)(.*)$/', $line, $matches) === 1) {
                $leadingWhitespace = $matches[1];
                $ruleName = $matches[2];
                $operatorSpacing = $matches[3];
                $operator = $matches[4];
                $tail = $matches[5];
                $active = in_array($ruleName, $activeRuleNames, true);
                $activeIndex = $this->firstActiveRuleIndex($ruleName, $activeRuleNames);

                $color = $activeColors[$ruleName] ?? $this->colorForKey($ruleName);
                $renderedPrefix = $this->style($useColor, '2', $active && $focusRuleName !== null && $ruleName === $focusRuleName ? '> ' : '- ');

                if (!$active || $activeIndex === null) {
                    $lines[] = $renderedPrefix . $line;

                    continue;
                }

                $renderedRuleName = $this->style($useColor, '1;' . $color, $ruleName);
                $renderedOperator = $this->style($useColor, '2', $operatorSpacing . $operator);
                $renderedTail = $tail === '' ? '' : $this->highlightGrammarTail(
                    $tail,
                    $activeRuleNames,
                    $activeIndex,
                    $focusToken,
                    $useColor,
                );

                $lines[] = $renderedPrefix . $leadingWhitespace . $renderedRuleName . $renderedOperator . $renderedTail;

                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Highlights the current focus token inside a rule tail.
     *
     * @param list<string> $activeRuleNames
     */
    private function highlightGrammarTail(string $tail, array $activeRuleNames, int $activeIndex, ?string $focusToken, bool $useColor): string
    {
        $activeColors = $this->activeRuleColors($activeRuleNames);
        $tokens = [];

        for ($i = $activeIndex + 1, $count = count($activeRuleNames); $i < $count; $i++) {
            $ruleName = $activeRuleNames[$i];
            $tokens[] = [
                'token' => $ruleName,
                'style' => '1;' . ($activeColors[$ruleName] ?? $this->colorForKey($ruleName)),
            ];
        }

        if ($focusToken !== null && $focusToken !== '') {
            $tail = $this->highlightFirstTokenOccurrence($tail, $focusToken, '1;43;30', $useColor);
        }

        return $this->highlightTokens($tail, $tokens, $useColor);
    }

    /**
     * Renders an expression or rule tree node.
     *
     * @param array<string, mixed> $node
     * @param list<int|string> $activePath
     * @return list<string>
     */
    private function renderNode(array $node, array $activePath, int $indentLevel, bool $useColor, bool $isRule = false): array
    {
        $indent = str_repeat('  ', $indentLevel);
        $id = $node['id'] ?? null;
        $active = $id !== null && in_array($id, $activePath, true);
        $kind = (string) ($node['kind'] ?? 'node');
        $label = (string) ($node['label'] ?? $node['name'] ?? $kind);

        if ($isRule) {
            $prefix = $active ? '>' : '-';
            $headline = sprintf('%s %s [%s]', $prefix, $label, $kind);
            if (!empty($node['water'])) {
                $headline .= ' water';
            }
            if (!empty($node['stateful'])) {
                $headline .= ' stateful';
            }
        } else {
            $prefix = $active ? '>' : '-';
            $headline = sprintf('%s %s [%s]', $prefix, $label, $kind);
        }

        $style = $active ? '1;33' : '37';
        $lines = [$indent . $this->style($useColor, $style, $headline)];

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $lines = array_merge($lines, $this->renderNode($child, $activePath, $indentLevel + 1, $useColor, false));
            }
        }

        if ($isRule && isset($node['expression']) && is_array($node['expression'])) {
            $lines = array_merge($lines, $this->renderNode($node['expression'], $activePath, $indentLevel + 1, $useColor, false));
        }

        return $lines;
    }

    /**
     * Renders the input with consumed and active ranges highlighted.
     *
     * @param list<array<string, mixed>> $steps
     */
    private function renderInput(string $input, array $steps, int $stepIndex, bool $useColor): string
    {
        $pastRanges = [];
        for ($i = 0; $i <= $stepIndex; $i++) {
            $step = $steps[$i] ?? null;
            if (!is_array($step) || ($step['phase'] ?? null) !== 'exit' || empty($step['success'])) {
                continue;
            }

            if (!isset($step['offset'], $step['endOffset'])) {
                continue;
            }

            $pastRanges[] = [
                'start' => (int) $step['offset'],
                'end' => (int) $step['endOffset'],
                'active' => false,
            ];
        }

        $activeRange = null;
        $currentStep = $steps[$stepIndex] ?? null;
        if (is_array($currentStep) && !empty($currentStep['success']) && isset($currentStep['offset'], $currentStep['endOffset'])) {
            $activeRange = [
                'start' => (int) $currentStep['offset'],
                'end' => (int) $currentStep['endOffset'],
                'active' => true,
            ];
        }

        $failedOffsets = [];
        if (is_array($currentStep) && empty($currentStep['success']) && isset($currentStep['offset'])) {
            $failedOffsets[] = (int) $currentStep['offset'];
        }

        $buffer = '';
        $chars = preg_split('//u', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $index => $char) {
            $style = '0';

            foreach ($pastRanges as $range) {
                if ($index >= $range['start'] && $index < $range['end']) {
                    $style = '42;30';
                }
            }

            if ($activeRange !== null && $index >= $activeRange['start'] && $index < $activeRange['end']) {
                $style = '43;30';
            }

            if (in_array($index, $failedOffsets, true)) {
                $style = '41;97';
            }

            $buffer .= $this->style($useColor, $style, $char);
        }

        return $buffer;
    }

    /**
     * Renders the legend for input highlighting colors.
     *
     * @return list<string>
     */
    private function renderInputLegend(bool $useColor): array
    {
        return [
            '',
            $this->style($useColor, '2', 'Legend'),
            '  ' . $this->style($useColor, '42;30', 'consumed input') . '  input that has already matched',
            '  ' . $this->style($useColor, '43;30', 'current input') . '  input matched by the current successful step',
            '  ' . $this->style($useColor, '41;97', 'match failure') . '  input position where the current step failed',
        ];
    }

    /**
     * Applies ANSI styles when enabled.
     */
    private function style(bool $useColor, string $style, string $text): string
    {
        if (!$useColor || $style === '0') {
            return $text;
        }

        return sprintf("\033[%sm%s\033[0m", $style, $text);
    }

    /**
     * Resolves the active node chain from the grammar snapshot and trace path.
     *
     * @param array<string, mixed> $grammar
     * @param list<int|string> $activePath
     * @return list<array<string, mixed>>
     */
    private function resolveActiveNodes(array $grammar, array $activePath): array
    {
        $index = $this->indexGrammarNodes($grammar);
        $nodes = [];

        foreach ($activePath as $nodeId) {
            if (!is_string($nodeId) || !isset($index[$nodeId])) {
                continue;
            }

            $nodes[] = $index[$nodeId];
        }

        return $nodes;
    }

    /**
     * Indexes all grammar nodes by their stable id.
     *
     * @param array<string, mixed> $grammar
     * @return array<string, array<string, mixed>>
     */
    private function indexGrammarNodes(array $grammar): array
    {
        $index = [];

        foreach ($grammar['rules'] ?? [] as $rule) {
            if (is_array($rule)) {
                $this->indexNode($rule, $index);
            }
        }

        foreach ($grammar['lakeProfiles'] ?? [] as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            if (is_array($profile['expression'] ?? null)) {
                $this->indexNode($profile['expression'], $index);
            }
        }

        return $index;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private function indexNode(array $node, array &$index): void
    {
        $id = $node['id'] ?? null;
        if (is_string($id)) {
            $index[$id] = $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->indexNode($child, $index);
            }
        }

        if (isset($node['expression']) && is_array($node['expression'])) {
            $this->indexNode($node['expression'], $index);
        }
    }

    /**
     * Extracts the active rule names from the current path.
     *
     * @param list<array<string, mixed>> $activeNodes
     * @return list<string>
     */
    private function activeRuleNames(array $activeNodes): array
    {
        $names = [];

        foreach ($activeNodes as $node) {
            if (($node['kind'] ?? null) !== 'rule') {
                continue;
            }

            $name = (string) ($node['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Finds the first occurrence of a rule name in the active path.
     *
     * @param list<string> $activeRuleNames
     */
    private function firstActiveRuleIndex(string $ruleName, array $activeRuleNames): ?int
    {
        $index = array_search($ruleName, $activeRuleNames, true);

        return $index === false ? null : (int) $index;
    }

    /**
     * Returns the deepest active rule name.
     *
     * @param list<string> $activeRuleNames
     */
    private function focusRuleName(array $activeRuleNames): ?string
    {
        if ($activeRuleNames === []) {
            return null;
        }

        return $activeRuleNames[array_key_last($activeRuleNames)];
    }

    /**
     * Returns the most relevant completed step for focus highlighting.
     *
     * @param list<array<string, mixed>> $steps
     * @return array<string, mixed>|null
     */
    private function focusTraceStep(array $steps, int $stepIndex): ?array
    {
        $current = $steps[$stepIndex] ?? null;
        if (!is_array($current)) {
            return null;
        }

        if (($current['phase'] ?? null) !== 'exit' || empty($current['success'])) {
            return $current;
        }

        $frameId = $current['frameId'] ?? null;
        if (!is_int($frameId)) {
            return $current;
        }

        $descendant = $this->findLastSuccessfulDescendant($steps, $stepIndex, $frameId);
        return $descendant ?? $current;
    }

    /**
     * Finds the deepest successful descendant step for a given frame.
     *
     * @param list<array<string, mixed>> $steps
     * @return array<string, mixed>|null
     */
    private function findLastSuccessfulDescendant(array $steps, int $stepIndex, int $frameId): ?array
    {
        for ($index = $stepIndex - 1; $index >= 0; $index--) {
            $step = $steps[$index] ?? null;
            if (!is_array($step)) {
                continue;
            }

            if (($step['phase'] ?? null) !== 'exit' || empty($step['success'])) {
                continue;
            }

            if (($step['parentFrameId'] ?? null) !== $frameId) {
                continue;
            }

            $childFrameId = $step['frameId'] ?? null;
            if (is_int($childFrameId)) {
                $nested = $this->findLastSuccessfulDescendant($steps, $index, $childFrameId);
                return $nested ?? $step;
            }

            return $step;
        }

        return null;
    }

    /**
     * Returns the source token text for the current focus node.
     *
     * @param array<string, mixed>|null $node
     */
    private function tokenForStep(?array $step): ?string
    {
        if ($step === null) {
            return null;
        }

        $target = $step['target'] ?? null;
        if (!is_array($target)) {
            return null;
        }

        return $this->tokenForTarget($target);
    }

    /**
     * Returns the source token text for a trace target.
     *
     * @param array<string, mixed> $target
     */
    private function tokenForTarget(array $target): ?string
    {
        $kind = (string) ($target['kind'] ?? '');

        if ($kind === '') {
            return null;
        }

        return match ($kind) {
            'rule' => (string) ($target['name'] ?? ''),
            'literal' => '"' . (string) ($target['literal'] ?? '') . '"',
            'regex' => "r'" . $this->escapeSingleQuoted((string) ($target['pattern'] ?? '')) . "'",
            'char-class' => (string) ($target['pattern'] ?? ''),
            'rule-reference' => (string) ($target['ruleName'] ?? ''),
            'named-capture' => $this->namedCaptureToken($target),
            'lake' => (string) ($target['name'] ?? '~'),
            default => (string) ($target['label'] ?? $target['name'] ?? ''),
        };
    }

    /**
     * Returns the source token text for the current focus node.
     *
     * @param array<string, mixed>|null $node
     */
    private function tokenForNode(?array $node): ?string
    {
        if ($node === null) {
            return null;
        }

        $kind = (string) ($node['kind'] ?? '');

        return match ($kind) {
            'literal' => '"' . (string) ($node['literal'] ?? '') . '"',
            'regex' => "r'" . $this->escapeSingleQuoted((string) ($node['pattern'] ?? '')) . "'",
            'char-class' => (string) ($node['pattern'] ?? ''),
            'rule-reference' => (string) ($node['ruleName'] ?? ''),
            'named-capture' => $this->namedCaptureToken($node),
            'lake' => (string) ($node['name'] ?? '~'),
            default => (string) ($node['label'] ?? $node['name'] ?? ''),
        };
    }

    /**
     * Builds the textual token for a named capture node.
     *
     * @param array<string, mixed> $node
     */
    private function namedCaptureToken(array $node): string
    {
        $name = (string) ($node['name'] ?? '');
        $children = $node['children'] ?? [];

        if ($name === '') {
            return '';
        }

        if (is_array($children) && isset($children[0]) && is_array($children[0])) {
            $childToken = $this->tokenForNode($children[0]);
            if ($childToken !== null && $childToken !== '') {
                return $name . '@' . $childToken;
            }
        }

        return $name;
    }

    /**
     * Escapes a string for a single-quoted source token.
     */
    private function escapeSingleQuoted(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /**
     * Highlights one or more tokens inside a string.
     *
     * @param list<array{token:string,style:string}> $tokens
     */
    private function highlightTokens(string $text, array $tokens, bool $useColor): string
    {
        usort($tokens, static fn (array $left, array $right): int => strlen($right['token']) <=> strlen($left['token']));

        foreach ($tokens as $token) {
            if ($token['token'] === '') {
                continue;
            }

            $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($token['token'], '/') . '(?![A-Za-z0-9_])/';
            $text = preg_replace_callback(
                $pattern,
                function (array $matches) use ($useColor, $token): string {
                    return $this->style($useColor, $token['style'], $matches[0]);
                },
                $text,
            ) ?? $text;
        }

        return $text;
    }

    /**
     * Highlights only the first occurrence of a token inside a string.
     */
    private function highlightFirstTokenOccurrence(string $text, string $token, string $style, bool $useColor): string
    {
        if ($token === '') {
            return $text;
        }

        $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($token, '/') . '(?![A-Za-z0-9_])/';

        return preg_replace_callback(
            $pattern,
            function (array $matches) use ($useColor, $style): string {
                return $this->style($useColor, $style, $matches[0]);
            },
            $text,
            1,
        ) ?? $text;
    }

    /**
     * Returns a stable ANSI color code for a key.
     */
    private function colorForKey(string $key): string
    {
        $palette = ['31', '32', '33', '34', '35', '36', '91', '92', '93', '94', '95', '96'];
        $index = abs((int) crc32($key)) % count($palette);

        return $palette[$index];
    }

    /**
     * Assigns stable, low-collision colors to the active rule path.
     *
     * @param list<string> $activeRuleNames
     * @return array<string, string>
     */
    private function activeRuleColors(array $activeRuleNames): array
    {
        $palette = ['31', '32', '33', '34', '35', '36', '91', '92', '93', '94', '95', '96'];
        $usedColors = [];
        $colors = [];

        foreach ($activeRuleNames as $ruleName) {
            if (isset($colors[$ruleName])) {
                continue;
            }

            $seed = abs((int) crc32($ruleName)) % count($palette);
            $attempts = 0;

            while ($attempts < count($palette)) {
                $color = $palette[$seed];
                if (!in_array($color, $usedColors, true)) {
                    $colors[$ruleName] = $color;
                    $usedColors[] = $color;

                    continue 2;
                }

                $seed = ($seed + 1) % count($palette);
                $attempts++;
            }

            $colors[$ruleName] = $palette[$seed];
            $usedColors[] = $palette[$seed];
        }

        return $colors;
    }
}
