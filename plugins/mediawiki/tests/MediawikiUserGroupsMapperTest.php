<?php
/**
 * Copyright (c) Enalean, 2014. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'bootstrap.php';

require_once dirname(__FILE__).'/../include/MediawikiUserGroupsMapper.class.php';

class MediawikiUserGroupsMapperTest extends TuleapTestCase {

    /** @var MediawikiDao */
    private $dao;

    /** @var MediawikiUserGroupsMapper */
    private $mapper;

    /** @var Project */
    private $project;

    public function setUp() {
        parent::setUp();

        $this->dao         = mock('MediawikiDao');
        $this->mapper      = new MediawikiUserGroupsMapper($this->dao);
        $this->project     = mock('Project');
    }

    public function itAddsAllUsersAsAnonymous() {
        stub($this->dao)->getMediawikiUserGroupMapping()->returnsEmptyDar();

        $new_mapping = array(
            'anonymous' => array(
                '1',
            ),
            'bot' => array(),
            'user' => array(),
            'sysop' => array(),
            'bureaucrat' => array()
        );

        expect($this->dao)->addMediawikiUserGroupMapping($this->project, MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_ANONYMOUS, '1')->once();
        expect($this->dao)->removeMediawikiUserGroupMapping()->never();
        $this->mapper->saveMapping($new_mapping, $this->project);
    }

    public function itRemovesAllUsersFromAnonymousStatus() {
        stub($this->dao)->getMediawikiUserGroupMapping()->returnsDar(
            array('group_id' => '104', 'ugroup_id' => '1', 'mw_group_name' => MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_ANONYMOUS)
        );

        $new_mapping = array(
            'anonymous' => array(),
            'bot' => array(),
            'user' => array(),
            'sysop' => array(),
            'bureaucrat' => array()
        );

        expect($this->dao)->removeMediawikiUserGroupMapping($this->project, MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_ANONYMOUS, '1')->once();
        expect($this->dao)->addMediawikiUserGroupMapping()->never();
        $this->mapper->saveMapping($new_mapping, $this->project);
    }

    public function itCallsRemoveAndAddDAOMethodsDuringSave() {
        stub($this->dao)->getMediawikiUserGroupMapping()->returnsDar(
            array(
                'group_id'      => 104,
                'ugroup_id'     => 1,
                'mw_group_name' => MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_ANONYMOUS
            ),
            array(
                'group_id'      => 104,
                'ugroup_id'     => 2,
                'mw_group_name' => MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_USER
            ),
            array(
                'group_id'      => 104,
                'ugroup_id'     => 3,
                'mw_group_name' => MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_USER
            ),
            array(
                'group_id'      => 104,
                'ugroup_id'     => 4,
                'mw_group_name' => MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_SYSOP
            ),
            array(
                'group_id'      => 104,
                'ugroup_id'     => 4,
                'mw_group_name' => MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_BUREAUCRAT
            )
        );

        $new_mapping = array(
            'anonymous' => array(
                '1',
                '2'
            ),
            'bot' => array(
                '3',
                '2',
                '4'
            ),
            'user' => array(
                '2',
                '4'
            ),
            'sysop' => array(
                '1'
            ),
            'bureaucrat' => array(
                '1'
            )
        );

        expect($this->dao)->removeMediawikiUserGroupMapping()->count(3);
        expect($this->dao)->removeMediawikiUserGroupMapping($this->project, MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_BUREAUCRAT, '4')->at(3);

        expect($this->dao)->addMediawikiUserGroupMapping()->count(7);
        expect($this->dao)->addMediawikiUserGroupMapping($this->project, MediawikiUserGroupsMapper::MEDIAWIKI_GROUPS_BUREAUCRAT, '1')->at(7);
        $this->mapper->saveMapping($new_mapping, $this->project);


    }
}