<?php

namespace RESTful\Test;

\RESTful\Bootstrap::init();
\Httpful\Bootstrap::init();

use RESTful\URISpec;
use RESTful\Client;
use RESTful\Registry;
use RESTful\Fields;
use RESTful\Query;
use RESTful\Page;

class Settings
{
    public static $url_root = 'http://api.example.com';

    public static $agent = 'example-php';

    public static $version = '0.1.0';

    public static $api_key = null;
}

class Resource extends \RESTful\Resource
{
    public static $fields, $f;

    protected static $_client, $_registry, $_uri_spec;

    public static function init()
    {
        self::$_client = new Client('Settings');
        self::$_registry = new Registry();
        self::$f = self::$fields = new Fields();
    }

    public static function getClient()
    {
        $class = get_called_class();
        return $class::$_client;
    }

    public static function getRegistry()
    {
        $class = get_called_class();
        return $class::$_registry;
    }

    public static function getURISpec()
    {
        $class = get_called_class();
        return $class::$_uri_spec;
    }
}

Resource::init();

class A extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('as', 'id', '/');
        self::$_registry->add(get_called_class());
    }
}

A::init();

class B extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('bs', 'id', '/');
        self::$_registry->add(get_called_class());
    }
}

B::init();

class URISpecTest extends \PHPUnit_Framework_TestCase
{
    public function testNoRoot()
    {
        $uri_spec = new URISpec('grapes', 'seed');
        $this->assertEquals($uri_spec->collection_uri, null);

        $result = $uri_spec->match('/some/raisins');
        $this->assertEquals($result, null);

        $result = $uri_spec->match('/some/grapes');
        $this->assertEquals($result, array('collection' => true));

        $result = $uri_spec->match('/some/grapes/1234');
        $expected = array(
            'collection' => false,
            'ids' => array('seed' => '1234')
            );
        $this->assertEquals($expected, $result);
    }

    public function testSingleId()
    {
        $uri_spec = new URISpec('tomatoes', 'stem', '/v1');
        $this->assertNotEquals($uri_spec->collection_uri, null);

        $result = $uri_spec->match('/some/tomatoes/that/are/green');
        $this->assertEquals($result, null);

        $result = $uri_spec->match('/some/tomatoes');
        $this->assertEquals($result, array('collection' => true));

        $result = $uri_spec->match('/some/tomatoes/4321');
        $expected = array(
            'collection' => false,
            'ids' => array('stem' => '4321')
            );
        $this->assertEquals($expected, $result);
    }

    public function testMultipleIds()
    {
        $uri_spec = new URISpec('tomatoes', array('stem', 'root'), '/v1');
        $this->assertNotEquals($uri_spec->collection_uri, null);

        $result = $uri_spec->match('/some/tomatoes/that/are/green');
        $this->assertEquals($result, null);

        $result = $uri_spec->match('/some/tomatoes');
        $this->assertEquals($result, array('collection' => true));

        $result = $uri_spec->match('/some/tomatoes/4321/1234');
        $expected = array(
            'collection' => false,
            'ids' => array('stem' => '4321', 'root' => '1234')
            );
        $this->assertEquals($expected, $result);
    }
}

class QueryTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        $uri = '/some/uri?field2=123&sort=field5%2Cdesc&limit=101&field3.field4%5Bcontains%5D=hi';
        $query = new Query('Resource', $uri);
        $expected = array(
            'field2' => array('123'),
            'field3.field4[contains]' => array('hi')
            );
        $this->assertEquals($query->filters, $expected);
        $expected = array('field5,desc');
        $this->assertEquals($query->sorts, $expected);
        $this->assertEquals($query->size, 101);
    }

    public function testBuild()
    {
        $query = new Query('Resource', '/some/uri');
        $query->filter(Resource::$f->name->eq('Wonka Chocs'))
              ->filter(Resource::$f->support_email->endswith('gmail.com'))
              ->filter(Resource::$f->variable_fee_percentage->gte(3.5))
              ->sort(Resource::$f->name->asc())
              ->sort(Resource::$f->variable_fee_percentage->desc())
              ->limit(101);
        $this->assertEquals(
            $query->filters,
            array(
                'name' => array('Wonka Chocs'),
                'support_email[contains]' => array('gmail.com'),
                'variable_fee_percentage[>=]'=> array(3.5)
                )
            );
        $this->assertEquals(
            $query->sorts,
            array('name,asc', 'variable_fee_percentage,desc')
            );
        $this->assertEquals(
            $query->size,
            101
            );
    }
}

class PageTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $data = new \stdClass();
        $data->first_uri = 'some/first/uri';
        $data->previous_uri = 'some/previous/uri';
        $data->next_uri = null;
        $data->last_uri = 'some/last/uri';
        $data->limit= 25;
        $data->offset = 0;
        $data->total = 101;
        $data->items = array();

        $page = new Page(
            'Resource',
            '/some/uri',
            $data
            );

        $this->assertEquals($page->resource, 'Resource');
        $this->assertEquals($page->total, 101);
        $this->assertEquals($page->items, array());
        $this->assertTrue($page->hasPrevious());
        $this->assertFalse($page->hasNext());
    }
}

class ResourceTest extends \PHPUnit_Framework_TestCase
{
    public function testQuery()
    {
        $query = A::query();
        $this->assertEquals(get_class($query), 'RESTful\Query');
    }

    public function testObjectify()
    {
        $a = new A(array(
            'uri' => '/as/123',
            'field1' => 123,
            'b' => array(
                'uri' => '/bs/321',
                'field2' => 321
            ))
        );
        $this->assertEquals(get_class($a), 'RESTful\Test\A');
        $this->assertEquals($a->field1, 123);
        $this->assertEquals(get_class($a->b), 'RESTful\Test\B');
        $this->assertEquals($a->b->field2, 321);
    }
}
