<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\App\Trace;

use EmanueleCoppola\PHPeg\Result\MatchResult;

/**
 * Collects parser trace steps during a parse run.
 */
class ParserTraceRecorder
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $steps = [];

    /**
     * @var list<int>
     */
    private array $frameStack = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $frameTargets = [];

    /**
     * @var list<string>
     */
    private array $nodeStack = [];

    /**
     * @var int
     */
    private int $nextFrameId = 1;

    /**
     * Starts a new trace frame and records the entry step.
     *
     * @param array<string, mixed> $target
     */
    public function enter(string $scope, array $target, int $offset, bool $silent): int
    {
        $frameId = $this->nextFrameId++;
        $parentFrameId = $this->frameStack === [] ? null : $this->frameStack[array_key_last($this->frameStack)];
        $this->frameStack[] = $frameId;
        $this->frameTargets[$frameId] = $target;
        $nodeId = (string) ($target['id'] ?? $scope . ':' . $frameId);
        $this->nodeStack[] = $nodeId;

        $this->steps[] = [
            'index' => count($this->steps),
            'phase' => 'enter',
            'scope' => $scope,
            'target' => $target,
            'frameId' => $frameId,
            'parentFrameId' => $parentFrameId,
            'offset' => $offset,
            'endOffset' => null,
            'success' => null,
            'silent' => $silent,
            'depth' => count($this->frameStack) - 1,
            'path' => $this->nodeStack,
            'message' => $this->formatEnterMessage($scope, $target, $offset, $silent),
        ];

        return $frameId;
    }

    /**
     * Closes a trace frame and records the exit step.
     */
    public function exit(int $frameId, bool $success, ?MatchResult $result): void
    {
        $parentFrameId = null;
        if (count($this->frameStack) >= 2) {
            $parentFrameId = $this->frameStack[count($this->frameStack) - 2];
        }

        $this->steps[] = [
            'index' => count($this->steps),
            'phase' => 'exit',
            'scope' => 'result',
            'target' => $this->frameTarget($frameId),
            'frameId' => $frameId,
            'parentFrameId' => $parentFrameId,
            'offset' => $result?->startOffset(),
            'endOffset' => $result?->endOffset(),
            'success' => $success,
            'silent' => false,
            'depth' => max(count($this->frameStack) - 1, 0),
            'path' => $this->nodeStack,
            'message' => $this->formatExitMessage($frameId, $success, $result),
        ];

        $this->popFrame($frameId);
        unset($this->frameTargets[$frameId]);
        array_pop($this->nodeStack);
    }

    /**
     * Returns the collected trace steps.
     *
     * @return list<array<string, mixed>>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * Returns the currently active trace path.
     *
     * @return list<int>
     */
    public function path(): array
    {
        return $this->frameStack;
    }

    /**
     * Returns the target metadata for an active frame.
     *
     * @return array<string, mixed>|null
     */
    private function frameTarget(int $frameId): ?array
    {
        return $this->frameTargets[$frameId] ?? null;
    }

    /**
     * Removes the active frame from the stack.
     */
    private function popFrame(int $frameId): void
    {
        if ($this->frameStack === []) {
            return;
        }

        $lastIndex = array_key_last($this->frameStack);
        if ($this->frameStack[$lastIndex] === $frameId) {
            array_pop($this->frameStack);

            return;
        }

        $this->frameStack = array_values(array_filter(
            $this->frameStack,
            static fn (int $candidate): bool => $candidate !== $frameId,
        ));
    }

    /**
     * Formats a human-readable entry message.
     *
     * @param array<string, mixed> $target
     */
    private function formatEnterMessage(string $scope, array $target, int $offset, bool $silent): string
    {
        $label = (string) ($target['label'] ?? $target['name'] ?? $target['description'] ?? $scope);

        return sprintf(
            '%s %s at offset %d%s',
            ucfirst($scope),
            $label,
            $offset,
            $silent ? ' (silent)' : '',
        );
    }

    /**
     * Formats a human-readable exit message.
     */
    private function formatExitMessage(int $frameId, bool $success, ?MatchResult $result): string
    {
        $target = $this->frameTarget($frameId) ?? [];
        $label = (string) ($target['label'] ?? $target['name'] ?? $target['description'] ?? 'step');

        if ($result === null) {
            return sprintf('%s failed', $label);
        }

        return sprintf(
            '%s %s %d → %d',
            $label,
            $success ? 'matched' : 'did not match',
            $result->startOffset(),
            $result->endOffset(),
        );
    }
}
