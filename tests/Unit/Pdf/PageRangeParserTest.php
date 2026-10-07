<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Exceptions\ValidationException;
use App\Pdf\PageRangeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageRangeParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{int, int}>}>
     */
    public static function valid(): iterable
    {
        yield 'spec example' => ["1-3\n5\n8-12", [[1, 3], [5, 5], [8, 12]]];
        yield 'commas and spaces' => ['1 - 3 , 5,8-12', [[1, 3], [5, 5], [8, 12]]];
        yield 'semicolons' => ['2;4', [[2, 2], [4, 4]]];
        yield 'open end' => ['8-', [[8, 12]]];
        yield 'en dash' => ['1–2', [[1, 2]]];
        yield 'single page doc' => ['12', [[12, 12]]];
        yield 'trailing separators' => [',1,,2,', [[1, 1], [2, 2]]];
        yield 'overlap allowed' => ['1-3,2-4', [[1, 3], [2, 4]]];
    }

    /**
     * @param list<array{int, int}> $expected
     */
    #[DataProvider('valid')]
    public function testValidInputs(string $input, array $expected): void
    {
        self::assertSame($expected, PageRangeParser::parse($input, 12));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', 'split.range_empty'];
        yield 'only separators' => [' , ; ', 'split.range_empty'];
        yield 'letters' => ['1-a', 'split.range_invalid'];
        yield 'double dash' => ['1--3', 'split.range_invalid'];
        yield 'leading dash' => ['-3', 'split.range_invalid'];
        yield 'sql' => ["1; DROP TABLE x", 'split.range_invalid'];
        yield 'zero' => ['0-2', 'split.range_zero'];
        yield 'reversed' => ['5-3', 'split.range_reversed'];
        yield 'beyond end' => ['10-13', 'split.range_out_of_bounds'];
        yield 'single beyond' => ['13', 'split.range_out_of_bounds'];
        yield 'huge number' => ['9999999', 'split.range_invalid'];
    }

    #[DataProvider('invalid')]
    public function testInvalidInputs(string $input, string $key): void
    {
        try {
            PageRangeParser::parse($input, 12);
            self::fail('Expected ValidationException for: ' . $input);
        } catch (ValidationException $e) {
            self::assertSame($key, $e->messageKey());
        }
    }

    public function testLabelsAndPages(): void
    {
        self::assertSame('5', PageRangeParser::label([5, 5]));
        self::assertSame('1-3', PageRangeParser::label([1, 3]));
        self::assertSame([8, 9, 10], PageRangeParser::pages([8, 10]));
    }
}
