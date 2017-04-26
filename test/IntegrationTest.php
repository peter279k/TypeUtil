<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class IntegrationTest extends \PHPUnit_Framework_TestCase {
    /** @dataProvider provideTests */
    public function testMutation(string $name, string $type, string $code, string $expected) {
        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments', 'startLine', 'startFilePos', 'endFilePos',
            ]
        ]);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer);

        switch ($type) {
        case 'add':
        case 'add-strict':
            $nameResolver = new NameResolver();
            $extractor = new TypeExtractor($nameResolver);

            $context = getContext($extractor, $nameResolver,
                $this->codeToAstStream($parser, $code));

            $strictTypes = $type === 'add-strict';
            $modifier = getAddModifier($nameResolver, $extractor, $context, $strictTypes);
            $result = $modifier($code, $parser->parse($code));
            break;
        case 'remove':
            $modifier = getRemoveModifier();
            $result = $modifier($code, $parser->parse($code));
            break;
        }

        $this->assertSame($expected, $result, $name);
    }

    public function provideTests() {
        $files = filesInDirs([__DIR__ . '/code'], 'php');
        foreach ($files as $file) {
            $name = $file->getPathName();
            $code = file_get_contents($name);
            list($orig, $expected) = explode('-----', $code);

            $ret = preg_match('/^#!([a-z-]+)\R/', $code, $matches);
            assert($ret === 1);

            $type = $matches[1];
            $orig = substr($orig, strlen($matches[0]));

            yield [$name, $type, $this->canonicalize($orig), $this->canonicalize($expected)];
        }
    }

    private function codeToAstStream(Parser $parser, string $code) : \Generator {
        yield [$code, $parser->parse($code)];
    }

    private function canonicalize(string $string) {
        $string = str_replace("\r\n", "\n", $string);
        return trim($string);
    }
}

