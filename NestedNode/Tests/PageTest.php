<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\NestedNode\Tests;

use BackBee\ClassContent\ContentSet;
use BackBee\MetaData\MetaDataBag;
use BackBee\NestedNode\Page;
use BackBee\Site\Layout;
use BackBee\Site\Site;
use BackBee\Tests\TestCase;
use BackBee\Workflow\State;

/**
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class PageTest extends TestCase
{
    /**
     * @var \Datetime
     */
    private $current_time;

    /**
     * @var \BackBee\NestedNode\Page
     */
    private $page;

    /**
     * @covers BackBee\NestedNode\Page::__construct
     */
    public function test__construct()
    {
        $page = new Page();

        $this->assertInstanceOf('BackBee\ClassContent\ContentSet', $page->getContentSet());
        $this->assertInstanceOf('Doctrine\Common\Collections\ArrayCollection', $page->getRevisions());
        $this->assertEquals(Page::STATE_HIDDEN, $page->getState());
        $this->assertFalse($page->isStatic());
        $this->assertEquals(Page::DEFAULT_TARGET, $page->getTarget());
    }

    /**
     * @covers BackBee\NestedNode\Page::__construct
     */
    public function test__constructWithOptions()
    {
        $this->assertEquals('title', $this->page->getTitle());
        $this->assertEquals('url', $this->page->getUrl());

        $pagef = new Page('test', 'not an array');
        $this->assertNull($pagef->getTitle());
        $this->assertNull($pagef->getUrl());
    }

    /**
     * @covers BackBee\NestedNode\Page::__clone
     */
    public function test__clone()
    {
        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setRoot($this->page)
                ->setParent($this->page)
                ->setLeftnode(2)
                ->setRightnode(3)
                ->setState(Page::STATE_ONLINE);

        $clone = clone $child;
        $this->assertNotEquals($child->getContentSet(), $clone->getContentSet());
        $this->assertNotEquals($child->getUid(), $clone->getUid());
        $this->assertEquals(1, $clone->getLeftnode());
        $this->assertEquals(2, $clone->getRightnode());
        $this->assertEquals(0, $clone->getLevel());
        $this->assertEquals($this->current_time, $clone->getCreated());
        $this->assertEquals($this->current_time, $clone->getModified());
        $this->assertNull($clone->getParent());
        $this->assertEquals($clone, $clone->getRoot());
        $this->assertEquals(Page::STATE_OFFLINE, $clone->getState());
        $this->assertFalse($clone->isStatic());
        $this->assertEquals($child->getTitle(), $clone->getTitle());
        $this->assertEquals($child->getUrl(), $clone->getUrl());
        $this->assertTrue(is_array($clone->cloning_datas));
        $this->assertTrue(isset($clone->cloning_datas['pages']));
        $this->assertTrue(isset($clone->cloning_datas['pages'][$child->getUid()]));
        $this->assertEquals($clone, $clone->cloning_datas['pages'][$child->getUid()]);

        $clone2 = clone $this->page;
        $this->assertEquals($this->page->getLayout(), $clone2->getLayout());
        $this->assertNotEquals($this->page->getContentSet(), $clone2->getContentSet());
    }

    /**
     * @covers BackBee\NestedNode\Page::getContentSet
     */
    public function testGetContentSet()
    {
        $this->assertInstanceOf('BackBee\ClassContent\ContentSet', $this->page->getContentSet());
    }

    /**
     * @covers BackBee\NestedNode\Page::getUrl
     */
    public function testGetUrl()
    {
        $this->assertEquals('url', $this->page->getUrl());

        $this->page->setRedirect('redirect');
        $this->assertEquals('redirect', $this->page->getUrl());
        $this->assertEquals('redirect', $this->page->getUrl(true));
        $this->assertEquals('url', $this->page->getUrl(false));
    }

    /**
     * @covers BackBee\NestedNode\Page::getNormalizeUri
     */
    public function testGetNormalizeUri()
    {
        $this->assertEquals('url', $this->page->getNormalizeUri());

        $site = new Site();
        $this->page->setSite($site);
        $this->assertEquals('url.html', $this->page->getNormalizeUri());
    }

    /**
     * @covers BackBee\NestedNode\Page::getTarget
     */
    public function testGetTarget()
    {
        $this->assertEquals(Page::DEFAULT_TARGET, $this->page->getTarget());

        $this->page->setTarget('target');
        $this->assertEquals('target', $this->page->getTarget());
    }

    /**
     * @covers BackBee\NestedNode\Page::isRedirect
     */
    public function testIsRedirect()
    {
        $this->assertFalse($this->page->isRedirect());

        $this->page->setRedirect('redirect');
        $this->assertTrue($this->page->isRedirect());
    }

    /**
     * @covers BackBee\NestedNode\Page::getRevisions
     */
    public function testGetRevisions()
    {
        $this->assertInstanceOf('Doctrine\Common\Collections\ArrayCollection', $this->page->getRevisions());
    }

    /**
     * @covers BackBee\NestedNode\Page::getData
     */
    public function testGetData()
    {
        $this->assertEquals($this->page->toArray(), $this->page->getData());
        $this->assertEquals('title', $this->page->getData('title'));
        $this->assertNull($this->page->getData('unknown'));
    }

    /**
     * @covers BackBee\NestedNode\Page::getParam
     */
    public function testGetParam()
    {
        $params = array(
            'left' => $this->page->getLeftnode(),
            'right' => $this->page->getRightnode(),
            'level' => $this->page->getLevel(),
        );

        $this->assertEquals($params, $this->page->getParam());
        $this->assertEquals($this->page->getLeftnode(), $this->page->getParam('left'));
        $this->assertEquals($this->page->getRightnode(), $this->page->getParam('right'));
        $this->assertEquals($this->page->getLevel(), $this->page->getParam('level'));
        $this->assertNull($this->page->getParam('unknown'));
    }

    /**
     * @covers BackBee\NestedNode\Page::isScheduled
     */
    public function testIsScheduled()
    {
        $this->assertFalse($this->page->isScheduled());

        $this->page->setPublishing(new \DateTime());
        $this->page->setArchiving();
        $this->assertTrue($this->page->isScheduled());

        $this->page->setPublishing();
        $this->page->setArchiving(new \DateTime());
        $this->assertTrue($this->page->isScheduled());

        $this->page->setPublishing(new \DateTime());
        $this->page->setArchiving(new \DateTime());
        $this->assertTrue($this->page->isScheduled());
    }

    /**
     * @covers BackBee\NestedNode\Page::isVisible
     */
    public function testIsVisble()
    {
        $this->assertFalse($this->page->isVisible());

        $this->page->setState(Page::STATE_ONLINE);
        $this->assertTrue($this->page->isVisible());

        $this->page->setState(Page::STATE_ONLINE & Page::STATE_HIDDEN);
        $this->assertFalse($this->page->isVisible());
    }

    /**
     * @covers BackBee\NestedNode\Page::isOnline
     */
    public function testIsOnline()
    {
        $this->assertFalse($this->page->isOnline());
        $this->assertFalse($this->page->isOnline(true));

        $this->page->setState(Page::STATE_ONLINE);
        $this->assertTrue($this->page->isOnline());
        $this->assertTrue($this->page->isOnline(true));

        $this->page->setState(Page::STATE_ONLINE + Page::STATE_HIDDEN);
        $this->assertTrue($this->page->isOnline());
        $this->assertTrue($this->page->isOnline(true));

        $this->page->setState(Page::STATE_ONLINE + Page::STATE_HIDDEN + Page::STATE_DELETED);
        $this->assertFalse($this->page->isOnline());
        $this->assertFalse($this->page->isOnline(true));

        $yesterday = new \DateTime('yesterday');
        $tomorrow = new \DateTime('tomorrow');

        $this->page->setState(Page::STATE_ONLINE)
                ->setPublishing($tomorrow)
                ->setArchiving();
        $this->assertFalse($this->page->isOnline());
        $this->assertTrue($this->page->isOnline(true));

        $this->page->setPublishing()
                ->setArchiving($yesterday);
        $this->assertFalse($this->page->isOnline());
        $this->assertTrue($this->page->isOnline(true));

        $this->page->setPublishing($yesterday)
                ->setArchiving($tomorrow);
        $this->assertTrue($this->page->isOnline());
        $this->assertTrue($this->page->isOnline(true));
    }

    /**
     * @covers BackBee\NestedNode\Page::isDeleted
     */
    public function testIsDeleted()
    {
        $this->assertFalse($this->page->isDeleted());

        $this->page->setState($this->page->getState() + Page::STATE_DELETED);
        $this->assertTrue($this->page->isDeleted());
    }

    /**
     * @covers BackBee\NestedNode\Page::setSite
     */
    public function testSetSite()
    {
        $site = new Site();
        $this->assertEquals($this->page, $this->page->setSite($site));
        $this->assertEquals($site, $this->page->getSite());

        $this->page->setSite();
        $this->assertNull($this->page->getSite());
    }

    /**
     * @covers BackBee\NestedNode\Page::setContentSet
     */
    public function testSetContentSet()
    {
        $contentset = new ContentSet();
        $this->assertEquals($this->page, $this->page->setContentSet($contentset));
        $this->assertEquals($contentset, $this->page->getContentSet());
    }

    /**
     * @covers BackBee\NestedNode\Page::setDate
     */
    public function testSetDate()
    {
        $this->assertEquals($this->page, $this->page->setDate($this->current_time));
        $this->assertEquals($this->current_time, $this->page->getDate());
        $this->assertEquals($this->page, $this->page->setDate(null));
        $this->assertNull($this->page->getDate());
    }

    /**
     * @covers BackBee\NestedNode\Page::setLayout
     * @covers BackBee\NestedNode\Page::getInheritedContent
     * @covers BackBee\NestedNode\Page::createNewDefaultContent
     */
    public function testSetLayout()
    {
        $this->assertEquals(2, $this->page->getContentSet()->count());
        $this->assertEquals(1, $this->page->getContentSet()->first()->count());
        $this->assertInstanceOf('BackBee\ClassContent\ContentSet', $this->page->getContentSet()->first()->first());
        $this->assertEquals($this->page, $this->page->getContentSet()->first()->first()->getMainNode());
        $this->assertEquals(0, $this->page->getContentSet()->last()->count());

        $topush = new ContentSet();
        $column = new ContentSet();
        $this->page->getContentSet()->last()->push($column);
        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setParent($this->page)
                ->setLayout($this->page->getLayout(), $topush);

        $this->assertEquals(2, $child->getContentSet()->count());
        $this->assertEquals(1, $child->getContentSet()->first()->count());
        $this->assertEquals($topush, $child->getContentSet()->first()->first());
        $this->assertEquals($child, $child->getContentSet()->first()->first()->getMainNode());
        $this->assertEquals(1, $child->getContentSet()->last()->count());
        $this->assertEquals($column, $child->getContentSet()->last()->first());
    }

    /**
     * @covers BackBee\NestedNode\Page::setAltTitle
     */
    public function testSetAltTitle()
    {
        $this->assertEquals($this->page, $this->page->setAltTitle('alt-title'));
        $this->assertEquals('alt-title', $this->page->getAltTitle());
    }

    /**
     * @covers BackBee\NestedNode\Page::setTitle
     */
    public function testSetTitle()
    {
        $this->assertEquals($this->page, $this->page->setTitle('new-title'));
        $this->assertEquals('new-title', $this->page->getTitle());
    }

    /**
     * @covers BackBee\NestedNode\Page::setUrl
     */
    public function testSetUrl()
    {
        $this->assertEquals($this->page, $this->page->setUrl('new-url'));
        $this->assertEquals('new-url', $this->page->getUrl());
    }

    /**
     * @covers BackBee\NestedNode\Page::setTarget
     */
    public function testSetTarget()
    {
        $this->assertEquals($this->page, $this->page->setTarget('target'));
        $this->assertEquals('target', $this->page->getTarget());
    }

    /**
     * @covers BackBee\NestedNode\Page::setRedirect
     */
    public function testSetRedirect()
    {
        $this->assertEquals($this->page, $this->page->setRedirect('redirect'));
        $this->assertEquals('redirect', $this->page->getRedirect());
    }

    /**
     * @covers BackBee\NestedNode\Page::setMetaData
     */
    public function testSetMetaData()
    {
        $meta = new MetaDataBag();
        $this->assertEquals($this->page, $this->page->setMetaData($meta));
        $this->assertEquals($meta, $this->page->getMetaData());
        $this->assertEquals($this->page, $this->page->setMetaData(null));
        $this->assertNull($this->page->getMetaData());
    }

    /**
     * @covers BackBee\NestedNode\Page::setState
     */
    public function testSetState()
    {
        $this->assertEquals($this->page, $this->page->setState(Page::STATE_DELETED));
        $this->assertEquals(Page::STATE_DELETED, $this->page->getState());
    }

    /**
     * @covers BackBee\NestedNode\Page::setPublishing
     */
    public function testSetPublishing()
    {
        $this->assertEquals($this->page, $this->page->setPublishing($this->current_time));
        $this->assertEquals($this->current_time, $this->page->getPublishing());
        $this->assertEquals($this->page, $this->page->setPublishing(null));
        $this->assertNull($this->page->getPublishing());
    }

    /**
     * @covers BackBee\NestedNode\Page::setArchiving
     */
    public function testSetArchiving()
    {
        $this->assertEquals($this->page, $this->page->setArchiving($this->current_time));
        $this->assertEquals($this->current_time, $this->page->getArchiving());
        $this->assertEquals($this->page, $this->page->setArchiving(null));
        $this->assertNull($this->page->getArchiving());
    }

    /**
     * @covers BackBee\NestedNode\Page::setRevisions
     */
    public function testSetRevisions()
    {
        $revisions = new \Doctrine\Common\Collections\ArrayCollection();
        $this->assertEquals($this->page, $this->page->setRevisions($revisions));
        $this->assertEquals($revisions, $this->page->getRevisions());
    }

    /**
     * @covers BackBee\NestedNode\Page::setWorkflowState
     */
    public function testSetWorkflowState()
    {
        $state = new State();
        $this->assertEquals($this->page, $this->page->setWorkflowState($state));
        $this->assertEquals($state, $this->page->getWorkflowState());
        $this->assertEquals($this->page, $this->page->setWorkflowState(null));
        $this->assertNull($this->page->getWorkflowState());
    }

    /**
     * @covers BackBee\NestedNode\Page::getInheritedContensetZoneParams
     */
    public function testGetInheritedContensetZoneParams()
    {
        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setParent($this->page);

        $this->assertNull($child->getInheritedContensetZoneParams($child->getContentSet()));
        $this->assertNull($child->getInheritedContensetZoneParams($child->getContentSet()));

        $child->setLayout($this->page->getLayout());

        $expected = $this->page->getLayout()->getZone(1);
        $this->assertEquals($expected, $child->getInheritedContensetZoneParams($child->getContentSet()->last()));
        $this->assertNull($child->getInheritedContensetZoneParams($child->getContentSet()->first()));
        $this->assertNull($this->page->getInheritedContensetZoneParams($this->page->getContentSet()->first()));
        $this->assertNull($this->page->getInheritedContensetZoneParams($this->page->getContentSet()->last()));
    }

    /**
     * @covers BackBee\NestedNode\Page::getRootContentSetPosition
     */
    public function testGetRootContentSetPosition()
    {
        $column1 = $this->page->getContentSet()->first();
        $column2 = $this->page->getContentSet()->last();

        $this->assertEquals(0, $this->page->getRootContentSetPosition($column1));
        $this->assertEquals(1, $this->page->getRootContentSetPosition($column2));
        $this->assertFalse($this->page->getRootContentSetPosition(new ContentSet()));
    }

    /**
     * @covers BackBee\NestedNode\Page::getParentZoneAtSamePositionIfExists
     */
    public function testGetParentZoneAtSamePositionIfExists()
    {
        $this->assertFalse($this->page->getParentZoneAtSamePositionIfExists($this->page->getContentSet()->first()));
        $this->assertFalse($this->page->getParentZoneAtSamePositionIfExists($this->page->getContentSet()->last()));

        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setParent($this->page)
                ->setLayout($this->page->getLayout());

        $this->assertFalse($child->getParentZoneAtSamePositionIfExists(new ContentSet()));
        $this->assertEquals($this->page->getContentSet()->first(), $child->getParentZoneAtSamePositionIfExists($child->getContentSet()->first()));
        $this->assertEquals($this->page->getContentSet()->last(), $child->getParentZoneAtSamePositionIfExists($child->getContentSet()->last()));

        $thirdcolumn = new \stdClass();
        $thirdcolumn->id = 'third';
        $thirdcolumn->defaultContainer = null;
        $thirdcolumn->target = '#target';
        $thirdcolumn->gridClassPrefix = 'row';
        $thirdcolumn->gridSize = 4;
        $thirdcolumn->mainZone = false;
        $thirdcolumn->defaultClassContent = 'inherited';
        $thirdcolumn->options = null;

        $data = $this->getDefaultLayoutZones();
        $data->templateLayouts[] = $thirdcolumn;

        $layout = new Layout();
        $child->setLayout($layout->setDataObject($data));
        $this->assertEquals($this->page->getContentSet()->last(), $child->getParentZoneAtSamePositionIfExists($child->getContentSet()->item(1)));
        $this->assertFalse($child->getParentZoneAtSamePositionIfExists($child->getContentSet()->last()));
    }

    /**
     * @covers BackBee\NestedNode\Page::getInheritedZones
     */
    public function testGetInheritedZones()
    {
        $this->assertEquals(array(), $this->page->getInheritedZones());

        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setParent($this->page)
                ->setLayout($this->page->getLayout());

        $this->assertEquals(array($child->getContentSet()->last()->getUid() => $child->getContentSet()->last()), $child->getInheritedZones());
        $this->assertEquals(array($child->getContentSet()->last()->getUid() => $child->getContentSet()->last()), $child->getInheritedZones(false));
        $this->assertEquals(array($child->getContentSet()->last()->getUid() => $child->getContentSet()->last()), $child->getInheritedZones(null));
        $this->assertEquals(array($child->getContentSet()->last()->getUid() => $child->getContentSet()->last()), $child->getInheritedZones('fake'));
        $this->assertEquals(array($child->getContentSet()->last()->getUid()), $child->getInheritedZones(true));
    }

    /**
     * @covers BackBee\NestedNode\Page::getPageMainZones
     */
    public function testGetPageMainZones()
    {
        $this->assertEquals(array($this->page->getContentSet()->first()->getUid() => $this->page->getContentSet()->first()), $this->page->getPageMainZones());

        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $this->assertEquals(array(), $child->getPageMainZones());
    }

    /**
     * @covers BackBee\NestedNode\Page::isLinkedToHisParentBy
     */
    public function testIsLinkedToHisParentBy()
    {
        $this->assertFalse($this->page->isLinkedToHisParentBy($this->page->getContentSet()->first()));
        $this->assertFalse($this->page->isLinkedToHisParentBy($this->page->getContentSet()->last()));
        $this->assertFalse($this->page->isLinkedToHisParentBy(null));

        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setParent($this->page)
                ->setLayout($this->page->getLayout());

        $this->assertFalse($child->isLinkedToHisParentBy($child->getContentSet()->first()));
        $this->assertTrue($child->isLinkedToHisParentBy($child->getContentSet()->last()));
    }

    /**
     * @covers BackBee\NestedNode\Page::replaceRootContentSet
     */
    public function testReplaceRootContentSet()
    {
        $oldContentSet = $this->page->getContentSet()->last();
        $newContentSet = new ContentSet();

        $this->assertEquals($newContentSet, $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $newContentSet));
        $this->assertEquals($oldContentSet, $this->page->getContentSet()->last());
        $this->assertEquals($newContentSet, $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $newContentSet, true));
        $this->assertEquals($oldContentSet, $this->page->getContentSet()->last());
        $this->assertEquals($newContentSet, $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $newContentSet, false));
        $this->assertEquals($newContentSet, $this->page->getContentSet()->last());

        $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $oldContentSet, false);
        $this->assertEquals($newContentSet, $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $newContentSet, null));
        $this->assertEquals($newContentSet, $this->page->getContentSet()->last());

        $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $oldContentSet, false);
        $this->assertEquals($newContentSet, $this->page->replaceRootContentSet($this->page->getContentSet()->last(), $newContentSet, 'fake'));
        $this->assertEquals($newContentSet, $this->page->getContentSet()->last());

        $child = new Page('child', array('title' => 'child', 'url' => 'url'));
        $child->setParent($this->page)
                ->setLayout($this->page->getLayout());

        $this->assertEquals($newContentSet, $child->replaceRootContentSet($child->getContentSet()->last(), $newContentSet));
        $this->assertEquals($newContentSet, $child->getContentSet()->last());
    }

    /**
     * @covers BackBee\NestedNode\Page::toArray
     */
    public function testToArray()
    {
        $expected = array(
            'id' => 'node_test',
            'rel' => 'leaf',
            'uid' => 'test',
            'rootuid' => 'test',
            'parentuid' => null,
            'created' => $this->current_time->getTimestamp(),
            'modified' => $this->current_time->getTimestamp(),
            'isleaf' => true,
            'siteuid' => null,
            'title' => 'title',
            'alttitle' => null,
            'url' => 'url',
            'target' => '_self',
            'redirect' => null,
            'state' => Page::STATE_HIDDEN,
            'date' => null,
            'publishing' => null,
            'archiving' => null,
            'metadata' => null,
            'layout_uid' => $this->page->getLayout()->getUid(),
            'workflow_state' => null,
        );

        $this->assertEquals($expected, $this->page->toArray());

        $this->page->setSite(new Site())
                ->setDate($this->current_time)
                ->setArchiving($this->current_time)
                ->setPublishing($this->current_time)
                ->setMetadata(new MetaDataBag())
                ->setWorkflowState(new State(null, array('code' => 1)));

        $expected = array(
            'id' => 'node_test',
            'rel' => 'leaf',
            'uid' => 'test',
            'rootuid' => 'test',
            'parentuid' => null,
            'created' => $this->current_time->getTimestamp(),
            'modified' => $this->current_time->getTimestamp(),
            'isleaf' => true,
            'siteuid' => $this->page->getSite()->getUid(),
            'title' => 'title',
            'alttitle' => null,
            'url' => 'url',
            'target' => '_self',
            'redirect' => null,
            'state' => Page::STATE_HIDDEN,
            'date' => $this->current_time->getTimestamp(),
            'publishing' => $this->current_time->getTimestamp(),
            'archiving' => $this->current_time->getTimestamp(),
            'metadata' => array(),
            'layout_uid' => $this->page->getLayout()->getUid(),
            'workflow_state' => 1,
        );

        $this->assertEquals($expected, $this->page->toArray());
    }

    /**
     * @covers BackBee\NestedNode\Page::serialize
     * @covers BackBee\NestedNode\Page::_setDateTimeValue
     */
    public function testUnserialize()
    {
        $this->page->setSite(new Site())
                ->setDate($this->current_time)
                ->setArchiving($this->current_time)
                ->setPublishing($this->current_time)
                ->setMetadata(new MetaDataBag());

        $new_page = new Page();
        $new_page->setSite($this->page->getSite())
                ->setLayout($this->page->getLayout())
                ->setContentSet($this->page->getContentSet());

        $this->assertEquals($this->page, $new_page->unserialize($this->page->serialize()));
    }

    /**
     * @covers BackBee\NestedNode\Page::getOldState
     */
    public function testGetOldState()
    {
        $this->assertEquals(null, $this->page->getOldState());
        $this->page->setOldState(Page::STATE_DELETED);
        $this->assertEquals(Page::STATE_DELETED, $this->page->getOldState());
    }

    /**
     * @covers BackBee\NestedNode\Page::setOldState
     */
    public function testSetOldState()
    {
        $this->assertEquals($this->page, $this->page->setOldState(Page::STATE_DELETED));
    }

    /**
     * @covers BackBee\NestedNode\Page::setUseUrlRedirect
     */
    public function testSetUseUrlRedirect()
    {
        $this->assertEquals($this->page, $this->page->setUseUrlRedirect(true));
    }

    /**
     * @covers BackBee\NestedNode\Page::getUseUrlRedirect
     */
    public function testGetUseUrlRedirect()
    {
        $this->assertEquals(true, $this->page->getUseUrlRedirect());
        $this->page->setUseUrlRedirect(false);
        $this->assertEquals(false, $this->page->getUseUrlRedirect());
    }

    /**
     * Sets up the fixture.
     */
    public function setUp()
    {
        $this->current_time = new \Datetime();
        $this->page = new Page('test', array('title' => 'title', 'url' => 'url'));

        $layout = new Layout();
        $this->page->setLayout($layout->setDataObject($this->getDefaultLayoutZones()));
    }

    /**
     * Builds a default set of layout zones.
     *
     * @return \stdClass
     */
    private function getDefaultLayoutZones()
    {
        $mainzone = new \stdClass();
        $mainzone->id = 'main';
        $mainzone->defaultContainer = null;
        $mainzone->target = '#target';
        $mainzone->gridClassPrefix = 'row';
        $mainzone->gridSize = 8;
        $mainzone->mainZone = true;
        $mainzone->defaultClassContent = 'ContentSet';
        $mainzone->options = null;

        $asidezone = new \stdClass();
        $asidezone->id = 'aside';
        $asidezone->defaultContainer = null;
        $asidezone->target = '#target';
        $asidezone->gridClassPrefix = 'row';
        $asidezone->gridSize = 4;
        $asidezone->mainZone = false;
        $asidezone->defaultClassContent = 'inherited';
        $asidezone->options = null;

        $data = new \stdClass();
        $data->templateLayouts = array(
            $mainzone,
            $asidezone,
        );

        return $data;
    }
}
