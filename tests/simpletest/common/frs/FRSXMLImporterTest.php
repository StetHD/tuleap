<?php
/**
 * Copyright (c) Sogilis, 2015. All Rights Reserved.
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

class FRSPackageFactoryMock extends FRSPackageFactory {
    // bypass it for the tests as it calls global functions which access to the db
    function setDefaultPermissions(FRSPackage $package) {
    }
}

class FRSXMLImporterTest_FRSFileFactory extends FRSFileFactory {

    function __construct(){
        parent::__construct();
        $this->fileforge = '/bin/true';
    }

}


class FRSXMLImporterTest_BootStrap extends TuleapTestCase {

    public function setUp() {
        $this->package_factory = new FRSPackageFactoryMock();
        $this->release_factory = new FRSReleaseFactory();
        $this->file_factory = new FRSXMLImporterTest_FRSFileFactory();

        $this->package_dao = mock('FRSPackageDao');
        $this->package_factory->dao = $this->package_dao;
        FRSPackageFactory::setInstance($this->package_factory);

        $this->permissions_manager = mock('PermissionsManager');
        PermissionsManager::setInstance($this->permissions_manager);

        $this->release_dao = mock('FRSReleaseDao');
        $this->release_factory->dao =  $this->release_dao;
        $this->release_factory->package_factory = $this->package_factory;
        $this->release_factory->file_factory = $this->file_factory;
        FRSReleaseFactory::setInstance($this->release_factory);

        $this->file_dao = mock('FRSFileDao');
        $this->file_factory->dao = $this->file_dao;
        $this->file_factory->release_factory = $this->release_factory;

        $this->processor_dao = mock('FRSProcessorDao');
        $this->filetype_dao = mock('FRSFileTypeDao');

        $this->user_finder = mock('User\XML\Import\IFindUserFromXMLReference');
        $this->user_manager = mock('UserManager');
        UserManager::setInstance($this->user_manager);

        $this->ugroup_dao = mock('UGroupDao');
        stub($this->ugroup_dao)->searchByGroupIdAndName()->returns(new DataAccessResultEmpty());

        $this->xml_import_helper = mock('XMLImportHelper');

        $this->frs_importer = new FRSXMLImporter(
            mock('Logger'),
            new XML_RNGValidator(),
            $this->package_factory,
            $this->release_factory,
            $this->file_factory,
            $this->user_finder,
            new UGroupManager($this->ugroup_dao),
            $this->xml_import_helper,
            $this->processor_dao,
            $this->filetype_dao);

        EventManager::setInstance(mock('EventManager'));
        $GLOBALS['Language'] = mock('BaseLanguage');
        if(isset($GLOBALS['ftp_incoming_dir'])) {
            $this->old_ftp_incoming_dir = $GLOBALS['ftp_incoming_dir'];
        }
        if(isset($GLOBALS['old_ftp_frs_dir_prefix'])) {
            $this->old_ftp_frs_dir_prefix = $GLOBALS['ftp_frs_dir_prefix'];
        }
        $GLOBALS['ftp_incoming_dir'] = parent::getTmpDir();
        $GLOBALS['ftp_frs_dir_prefix'] = parent::getTmpDir();
    }

    public function tearDown() {
        $GLOBALS['Language'] = null;
        PermissionsManager::clearInstance();
        UserManager::clearInstance();
        if(isset($this->old_ftp_incoming_dir)) {
            $GLOBALS['ftp_incoming_dir'] = $this->old_ftp_incoming_dir;
        } else {
            unset($GLOBALS['ftp_incoming_dir']);
        }
        if(isset($this->old_ftp_frs_dir_prefix)) {
            $GLOBALS['ftp_frs_dir_prefix'] = $this->old_ftp_frs_dir_prefix;
        } else {
            unset($GLOBALS['ftp_frs_dir_prefix']);
        }
    }

}

class FRSXMLImporterTest extends FRSXMLImporterTest_BootStrap {

    public function itShouldImportOnePackageWithDefaultValues() {
        $pm = ProjectManager::instance();
        $project = $pm->getProjectFromDbRow(array('group_id' => 123, 'unix_group_name' => 'test_project'));
        $xml = <<<XML
        <project>
            <frs>
                <package name="empty_package">
                    <read-access><ugroup>project_members</ugroup></read-access>
                </package>
            </frs>
        </project>
XML;
        $xml_element = new SimpleXMLElement($xml);
        $expected_package_array = $this->getDefaultPackage('empty_package');
        $this->package_dao->expectAt(0, 'createFromArray', array($expected_package_array));
        $this->package_dao->expectCallCount('createFromArray', 1);

        $this->frs_importer->import($project, $xml_element, '');
    }

    public function itShouldImportOnePackageWithOneRelease() {
        $pm = ProjectManager::instance();
        $project = $pm->getProjectFromDbRow(array('group_id' => 123, 'unix_group_name' => 'test_project'));
        $xml = <<<XML
        <project>
            <frs>
                <package name="package">
                    <read-access><ugroup>project_members</ugroup></read-access>
                    <release name="release" time="2015-12-03T14:55:00" preformatted="false">
                        <read-access><ugroup>project_members</ugroup></read-access>
                        <notes>some notes</notes>
                        <changes>some changes</changes>
                        <user format="username">toto</user>
                    </release>
                </package>
            </frs>
        </project>
XML;
        $xml_element = new SimpleXMLElement($xml);

        $user_id = 42;
        stub($this->user_finder)->getUser()->returns(new PFUser(array('user_id'=> $user_id)));


        $expected_package_array = $this->getDefaultPackage('package');
        $package_id = 1337;
        $this->package_dao->expectAt(0, 'createFromArray', array($expected_package_array));
        $this->package_dao->expectCallCount('createFromArray', 1);
        stub($this->package_dao)->createFromArray()->returns($package_id);

        $expected_release_array = array(
            'release_id' => null,
            'package_id' => $package_id,
            'name' => 'release',
            'notes' => 'some notes',
            'changes' => 'some changes',
            'status_id' => FRSRelease::STATUS_ACTIVE,
            'preformatted' => false,
            'release_date' => strtotime('2015-12-03T14:55:00'),
            'released_by' => $user_id);
        $this->release_dao->expectAt(0, 'createFromArray', array($expected_release_array));
        $this->release_dao->expectCallCount('createFromArray', 1);

        $this->frs_importer->import($project, $xml_element, '');
    }

    public function itShouldImportOnePackageWithOneReleaseWithOneFile() {
        $extraction_path = sys_get_temp_dir();
        $temp_file = tempnam($extraction_path, 'thefile_');
        fwrite(fopen($temp_file, 'w+'), 'such file, wow');
        $file_name = basename($temp_file);

        $pm = ProjectManager::instance();
        $project = $pm->getProjectFromDbRow(array('group_id' => 123, 'unix_group_name' => 'test_project'));
        $xml = <<<XML
        <project>
            <frs>
                <package name="package">
                    <read-access><ugroup>project_members</ugroup></read-access>
                    <release name="release" time="2015-12-03T14:55:00" preformatted="false">
                        <read-access><ugroup>project_members</ugroup></read-access>
                        <notes>some notes</notes>
                        <changes>some changes</changes>
                        <user format="username">toto</user>
                        <file src="$file_name" name="lefichier" release-time="2015-12-03T16:46:00" post-date="2015-12-03T16:46:42" arch="x86_64" filetype="Other">
                            <description>one file to rule them all</description>
                            <user format="username">toto</user>
                        </file>
                    </release>
                </package>
            </frs>
        </project>
XML;
        $xml_element = new SimpleXMLElement($xml);

        $user_id = 42;
        stub($this->user_finder)->getUser()->returns(new PFUser(array('user_id'=> $user_id)));

        $package_id = 1337;
        $package_array_with_id = array(
            'package_id' => $package_id,
            'group_id'   => 123,
            'name'       => "package",
            'status_id'  => FRSPackage::STATUS_ACTIVE,
            'rank'       => 0,
            'approve_license' => true
        );

        $expected_package_array = $this->getDefaultPackage('package');
        $this->package_dao->expectAt(0, 'createFromArray', array($expected_package_array));
        $this->package_dao->expectCallCount('createFromArray', 1);
        stub($this->package_dao)->createFromArray()->returns($package_id);
        stub($this->package_dao)->searchById($package_id, FRSPackageDao::INCLUDE_DELETED)->returnsDar($package_array_with_id);

        $release_id=8665;
        $expected_release_array = array(
            'release_id' => null,
            'package_id' => $package_id,
            'name' => 'release',
            'notes' => 'some notes',
            'changes' => 'some changes',
            'status_id' => FRSRelease::STATUS_ACTIVE,
            'preformatted' => false,
            'release_date' => strtotime('2015-12-03T14:55:00'),
            'released_by' => $user_id);
        $this->release_dao->expectAt(0, 'createFromArray', array($expected_release_array));
        $this->release_dao->expectCallCount('createFromArray', 1);
        stub($this->release_dao)->createFromArray()->returns($release_id);

        $release_array_with_group = $expected_release_array;
        $release_array_with_group['group_id'] = 123;

        stub($this->filetype_dao)->searchTypeId()->returns(667);
        stub($this->processor_dao)->searchProcessorId()->returns(69);
        stub($this->release_dao)->searchById()->returnsDar($release_array_with_group);
        stub($this->file_dao)->searchFileByName()->returnsEmptyDar();

        $file_id=12569;
        $expected_file_array = array(
            'file_id'       => null,
            'filename'      => "p1337_r8665/lefichier",
            'filepath'      => "p1337_r8665/lefichier_" . $_SERVER['REQUEST_TIME'],
            'release_id'    => $release_id,
            'type_id'       => 667,
            'processor_id'  => 69,
            'release_time'  => strtotime('2015-12-03T16:46:00'),
            'file_location' => $GLOBALS['ftp_frs_dir_prefix']."/test_project/p1337_r8665/lefichier_" . $_SERVER['REQUEST_TIME'],
            'file_size'     => '14',
            'post_date'     => strtotime('2015-12-03T16:46:42'),
            'status'        => "A",
            'computed_md5'  => "c58ef9ab0b1fc7f6f90ffb607dee0073",
            'reference_md5' => "c58ef9ab0b1fc7f6f90ffb607dee0073",
            'user_id'       => $user_id,
            'comment'       => "one file to rule them all");

        $this->file_dao->expectAt(0, 'createFromArray', array($expected_file_array));
        $this->file_dao->expectCallCount('createFromArray', 1);
        stub($this->file_dao)->createFromArray()->returns($file_id);

        $expected_file_array_with_id = $expected_file_array;
        $expected_file_array_with_id['id'] = $file_id;
        stub($this->file_dao)->searchById($file_id)->returnsDar($expected_file_array_with_id);

        $this->frs_importer->import($project, $xml_element, $extraction_path);
    }

    private function getDefaultPackage($name) {
        return array(
            'package_id' => null,
            'group_id' => 123,
            'name' => $name,
            'status_id' => FRSPackage::STATUS_ACTIVE,
            'rank' => 'end',
            'approve_license' => true);
    }
}


class FRSXMLImporter_Administrators_Test extends FRSXMLImporterTest_BootStrap {
    public function setUp() {
        parent::setUp();
        $this->ugroup_manager = mock('UGroupManager');
        $this->frs_importer = new FRSXMLImporter(
            mock('Logger'),
            new XML_RNGValidator(),
            $this->package_factory,
            $this->release_factory,
            $this->file_factory,
            $this->user_finder,
            $this->ugroup_manager,
            $this->xml_import_helper,
            $this->processor_dao,
            $this->filetype_dao);
    }

    public function itShouldImportAdministrators() {
        $pm = ProjectManager::instance();
        $project = $pm->getProjectFromDbRow(array('group_id' => 123, 'unix_group_name' => 'test_project'));
        $xml = <<<XML
        <project>
            <frs>
                <administrators>
                    <user format="username">adrien</user>
                </administrators>
            </frs>
        </project>
XML;
        $xml_element = new SimpleXMLElement($xml);
        $user_id = 42;
        $user = mock('PFUser');
        stub($user)->getId()->returns($user_id);
        stub($user)->isMember(123)->returns(true);
        stub($this->xml_import_helper)->getUser()->returns($user);
        $project_ugroup = mock('ProjectUGroup');
        stub($this->ugroup_manager)->getUGroupByName()->returns($project_ugroup);
        expect($project_ugroup)->addUser($user)->once();
        $this->frs_importer->import($project, $xml_element, '');
    }

    public function itShouldNotImportAdministratorsIfNotMemberOfTheProject() {
        $pm = ProjectManager::instance();
        $project = $pm->getProjectFromDbRow(array('group_id' => 123, 'unix_group_name' => 'test_project'));
        $xml = <<<XML
        <project>
            <frs>
                <administrators>
                    <user format="username">adrien</user>
                    <user format="username">toto</user>
                </administrators>
            </frs>
        </project>
XML;
        $xml_element = new SimpleXMLElement($xml);
        $user_adrien_id = 42;
        $user_adrien = mock('PFUser');
        stub($user_adrien)->getId()->returns($user_adrien_id);
        stub($user_adrien)->isMember(123)->returns(true);
        stub($this->xml_import_helper)->getUser()->returnsAt(0, $user_adrien);

        $user_toto_id = 1337;
        $user_toto = mock('PFUser');
        stub($user_toto)->getId()->returns($user_toto_id);
        stub($user_toto)->isMember(123)->returns(false);
        stub($this->xml_import_helper)->getUser()->returnsAt(1, $user_toto);

        $project_ugroup = mock('ProjectUGroup');
        stub($this->ugroup_manager)->getUGroupByName()->returns($project_ugroup);
        expect($project_ugroup)->addUser()->once();
        $this->frs_importer->import($project, $xml_element, '');
    }
}