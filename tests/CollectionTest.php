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

class CollectionTest extends SapphireTest
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

    public function testBuildAndDeleteCollection()
    {
        $this->markTestSkipped();
        $collection = $this->objFromFixture(Collection::class, 'test');
        $collection->write();
        $this->assertTrue($collection->ID > 0);
        $this->assertEquals($collection->ConnectionTimeout, 2);
        $this->assertEquals($collection->ImportLimit, 10000);
        $this->assertCount(3, $collection->Fields());

        $field1 = $collection->Fields()->first();
        $this->assertFalse((bool) $field1->facet);
        $this->assertFalse((bool) $field1->optional);
        $this->assertTrue((bool) $field1->index);
        $this->assertTrue((bool) $field1->sort);
        $this->assertTrue((bool) $field1->store);
        $this->assertFalse((bool) $field1->infix);

        $this->assertCount(3, $collection->Fields());

        $fieldsCount = Field::get();
        $this->assertCount(3, $fieldsCount);

        $collection->delete();
        $fieldsCount = Field::get();
        $this->assertCount(0, $collection->Fields());

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
