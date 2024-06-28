<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense\Tests;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Field;
use ElliotSawyer\SilverstripeTypesense\Typesense;
use Page;
use SilverStripe\Dev\SapphireTest;

class DocumentTest extends SapphireTest
{
    protected static $fixture_file = [
        'fixtures/Collection.yml'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $client = Typesense::client();
        $collection = $this->objFromFixture(Collection::class, 'test');
        $collection->syncWithTypesenseServer();
    }
    public function testOneOffAddAndRemoveDocument()
    {
        $client = Typesense::client();
        $collection = $this->objFromFixture(Collection::class, 'test');
        $this->assertTrue($client->collections[$collection->Name]->exists());

        $collectionResult = $client->collections[$collection->Name]->documents->search(['q' => '*']);
        $this->assertEquals(0, $collectionResult['found']);

        $home = $this->objFromFixture(Page::class, 'home');
        $home->publishRecursive();

        $collectionResult = $client->collections[$collection->Name]->documents->search(['q' => '*']);
        $this->assertEquals(1, $collectionResult['found']);

        $home->doUnpublish();
        $collectionResult = $client->collections[$collection->Name]->documents->search(['q' => '*']);
        $this->assertEquals(0, $collectionResult['found']);

        $home = $this->objFromFixture(Page::class, 'home');
        $home->publishRecursive();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $client = Typesense::client();
        try {
            @$client->collections['TestCollection']->delete();
        } catch (\Exception $e) {}
    }
}
