<?php

declare(strict_types=1);

namespace WordPressPsr\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WordPressPsr\Psr7\SimpleStream;

/**
 * Tests for SimpleStream — a minimal PSR-7 StreamInterface implementation.
 */
class SimpleStreamTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction and __toString
    // -------------------------------------------------------------------------

    public function testConstructorSetsContents(): void
    {
        $stream = new SimpleStream('hello world');

        $this->assertSame('hello world', (string) $stream);
    }

    public function testConstructorWithEmptyString(): void
    {
        $stream = new SimpleStream('');

        $this->assertSame('', (string) $stream);
    }

    // -------------------------------------------------------------------------
    // getSize
    // -------------------------------------------------------------------------

    public function testGetSizeReturnsContentLength(): void
    {
        $stream = new SimpleStream('hello');

        $this->assertSame(5, $stream->getSize());
    }

    public function testGetSizeForEmptyStream(): void
    {
        $stream = new SimpleStream('');

        $this->assertSame(0, $stream->getSize());
    }

    // -------------------------------------------------------------------------
    // isReadable / isWritable / isSeekable
    // -------------------------------------------------------------------------

    public function testIsReadableReturnsTrue(): void
    {
        $stream = new SimpleStream('data');

        $this->assertTrue($stream->isReadable());
    }

    public function testIsWritableReturnsTrue(): void
    {
        $stream = new SimpleStream('data');

        $this->assertTrue($stream->isWritable());
    }

    public function testIsSeekableReturnsFalse(): void
    {
        $stream = new SimpleStream('data');

        $this->assertFalse($stream->isSeekable());
    }

    // -------------------------------------------------------------------------
    // read
    // -------------------------------------------------------------------------

    public function testReadReturnsRequestedBytes(): void
    {
        $stream = new SimpleStream('hello world');

        $this->assertSame('hello', $stream->read(5));
    }

    public function testReadAdvancesPosition(): void
    {
        $stream = new SimpleStream('hello world');

        $stream->read(5); // consume 'hello'
        $this->assertSame(' worl', $stream->read(5));
    }

    public function testReadBeyondEndReturnsAvailableBytes(): void
    {
        $stream = new SimpleStream('hi');

        $result = $stream->read(100);
        $this->assertSame('hi', $result);
    }

    // -------------------------------------------------------------------------
    // write
    // -------------------------------------------------------------------------

    public function testWriteAppendsContent(): void
    {
        $stream = new SimpleStream('hello');
        $stream->write(' world');

        $this->assertSame('hello world', (string) $stream);
    }

    public function testWriteReturnsNumberOfBytesWritten(): void
    {
        $stream = new SimpleStream('');
        $bytes  = $stream->write('test');

        $this->assertSame(4, $bytes);
    }

    public function testWriteUpdatesSize(): void
    {
        $stream = new SimpleStream('hello');
        $stream->write(' world');

        $this->assertSame(11, $stream->getSize());
    }

    // -------------------------------------------------------------------------
    // getContents
    // -------------------------------------------------------------------------

    public function testGetContentsReturnsFullContentFromStart(): void
    {
        $stream = new SimpleStream('hello world');

        $this->assertSame('hello world', $stream->getContents());
    }

    public function testGetContentsReturnsRemainingAfterRead(): void
    {
        $stream = new SimpleStream('hello world');
        $stream->read(6); // consume 'hello '

        $this->assertSame('world', $stream->getContents());
    }

    // -------------------------------------------------------------------------
    // rewind / seek / tell / eof
    // -------------------------------------------------------------------------

    public function testRewindResetsPosition(): void
    {
        $stream = new SimpleStream('hello');
        $stream->read(5);
        $stream->rewind();

        $this->assertSame('hello', $stream->read(5));
    }

    public function testTellReturnsZero(): void
    {
        $stream = new SimpleStream('hello');

        $this->assertSame(0, $stream->tell());
    }

    public function testEofReturnsFalseAtStart(): void
    {
        $stream = new SimpleStream('hello');

        $this->assertFalse($stream->eof());
    }

    public function testEofReturnsTrueAfterReadingPastEnd(): void
    {
        $stream = new SimpleStream('hi');
        $stream->read(10); // advance past end

        $this->assertTrue($stream->eof());
    }

    // -------------------------------------------------------------------------
    // detach / close
    // -------------------------------------------------------------------------

    public function testDetachReturnsNull(): void
    {
        $stream = new SimpleStream('data');

        $this->assertNull($stream->detach());
    }

    public function testCloseDoesNotThrow(): void
    {
        $stream = new SimpleStream('data');

        // Should not throw
        $stream->close();
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // getMetadata
    // -------------------------------------------------------------------------

    public function testGetMetadataReturnsArray(): void
    {
        $stream   = new SimpleStream('data');
        $metadata = $stream->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('seekable', $metadata);
        $this->assertFalse($metadata['seekable']);
    }

    public function testGetMetadataWithKeyReturnsValue(): void
    {
        $stream = new SimpleStream('data');

        $this->assertFalse($stream->getMetadata('seekable'));
    }

    public function testGetMetadataWithUnknownKeyReturnsNull(): void
    {
        $stream = new SimpleStream('data');

        $this->assertNull($stream->getMetadata('nonexistent'));
    }
}
