<?php

class FileTest extends \Codeception\Test\Unit
{
    protected static function getFiles()
    {
        $files = [ 'one', 'two', 'three', 'four', 'five', 'six', 'seven' ];

        return $files;
    }

    protected static function cleanFiles()
    {
        foreach (static::getFiles() as $file) {
            $filePath = codecept_output_dir($file);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function setUp()
    {
        static::cleanFiles();
    }

    public function tearDown()
    {
        static::cleanFiles();
    }

    public function filesDataSet()
    {
        foreach (static::getFiles() as $file) {
            yield $file => [ $file ];
        }
    }

    /**
     * @dataProvider filesDataSet
     */
    public function test_files_can_be_created_and_removed($file)
    {
        // Make sure no file initially exists.
        $filePath = codecept_output_dir($file);

        $this->assertFileNotExists($filePath);

        touch($filePath);

        $this->assertFileExists($filePath);
    }
}
