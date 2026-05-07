<?php

declare(strict_types=1);

namespace EmanueleCoppola\PHPeg\Tests\Loader\Peg;

use EmanueleCoppola\PHPeg\Expression\LakeExpression;
use EmanueleCoppola\PHPeg\Loader\Peg\PegGrammarParser;
use PHPUnit\Framework\TestCase;

class PegGrammarParserTest extends TestCase
{
    /**
     * Verifies PEG source can be parsed into a grammar.
     */
    public function testParsesPegSource(): void
    {
        $grammar = PegGrammarParser::parse('Start <- "{" <BodyWater> "}"');

        self::assertSame('Start', $grammar->startRule());
        self::assertTrue($grammar->parse('{abc}')->isSuccess());
        self::assertInstanceOf(LakeExpression::class, $grammar->rule('Start')?->expression()->expressions()[1] ?? null);
    }

    /**
     * Verifies PEG water annotations are parsed and used during lake matching.
     */
    public function testParsesPegWaterAnnotation(): void
    {
        $grammar = PegGrammarParser::parse(<<<'PEG'
Start <- "{" <Body> "}"
@water
Quoted <- '"' [^"]* '"'
PEG);

        $document = $grammar->parseDocument('{foo "bar" baz}');
        $body = $document->query('Body[kind="lake"]:first')->first();
        $quoted = $body?->childrenByName('Quoted')[0] ?? null;

        self::assertNotNull($body);
        self::assertSame('Body', $body?->name());
        self::assertCount(1, $body?->childrenByName('Quoted') ?? []);
        self::assertSame('water', $quoted?->attribute('kind'));
        self::assertSame('"bar"', $quoted?->text());
    }

    /**
     * Verifies PEG lake declarations can use a local water profile.
     */
    public function testParsesPegLakeProfileAnnotation(): void
    {
        $grammar = PegGrammarParser::parse(<<<'PEG'
<BodyWater> <- [^{}]+
Program <- "{" <BodyWater> "}"
@water
Whitespace <- [ \t\r\n]+
PEG);

        $document = $grammar->parseDocument('{foo bar}');
        $lake = $document->query('BodyWater[kind="lake"]:first')->first();
        $water = $document->query('BodyWater[kind="water"]:first')->first();

        self::assertNotNull($lake);
        self::assertNotNull($water);
        self::assertSame('foo bar', $lake?->text());
        self::assertSame('foo bar', $water?->text());
    }

    /**
     * Verifies case-insensitive annotations are inherited by nested rule references.
     */
    public function testParsesPegInsensitiveAnnotation(): void
    {
        $grammar = PegGrammarParser::parse(<<<'PEG'
@insensitive
Start <- Prefix
Prefix <- "abc"
PEG);

        self::assertTrue($grammar->parse('ABC')->isSuccess());
    }

    /**
     * Verifies a sensitive child rule can override an inherited insensitive scope.
     */
    public function testParsesPegSensitiveOverrideAnnotation(): void
    {
        $grammar = PegGrammarParser::parse(<<<'PEG'
@insensitive
Start <- SensitivePart
@sensitive
SensitivePart <- "Ab"
PEG);

        self::assertTrue($grammar->parse('Ab')->isSuccess());
        self::assertFalse($grammar->parse('ab')->isSuccess());
    }

    /**
     * Verifies the terminal suffix `i` overrides the surrounding sensitive scope.
     */
    public function testParsesPegInsensitiveTerminalSuffix(): void
    {
        $grammar = PegGrammarParser::parse(<<<'PEG'
@sensitive
Start <- "ab"i
PEG);

        self::assertTrue($grammar->parse('AB')->isSuccess());
    }
}
