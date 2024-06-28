<?php
/**
 * Silverstripe Typesense module
 * @license LGPL3 With Attribution
 * @copyright Copyright (C) 2024 Elliot Sawyer
 */
namespace ElliotSawyer\SilverstripeTypesense\Tests;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Field;
use Page;
use SilverStripe\Dev\SapphireTest;

class CollectionTest extends SapphireTest
{
    protected static $fixture_file = [
        'fixtures/Collection.yml'
    ];

    public function testBuildAndDeleteCollection()
    {
        $collection = $this->objFromFixture(Collection::class, 'test');
        $collection->write();
        $this->assertTrue($collection->ID > 0);
        $this->assertEquals($collection->ConnectionTimeout, 2);
        $this->assertEquals($collection->ImportLimit, 10000);
        $this->assertCount(0, $collection->Fields());

        $collection->Fields()->add(
            $field1 = $this->objFromFixture(Field::class, 'field1')
        );

        $this->assertFalse((bool) $field1->facet);
        $this->assertFalse((bool) $field1->optional);
        $this->assertTrue((bool) $field1->index);
        $this->assertTrue((bool) $field1->sort);
        $this->assertTrue((bool) $field1->store);
        $this->assertFalse((bool) $field1->infix);

        $this->assertCount(1, $collection->Fields());
    }
}
