<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 11.02.19
 * Time: 18:27
 */

use the16thpythonist\Wordpress\Test\JWPTestCase;
use the16thpythonist\Wordpress\Scopus\ScopusOptionsRegistration;

class ScopusOptionsTest extends JWPTestCase
{

    const DEFAULT_INSERT_USERDATA = array(
        'user_pass'         => 'simple',
        'user_login'        => 'scopus',
        'user_nicename'     => 'scopus',
        'display_name'      => 'Scopus',
        'role'              => 'author',
    );

    // *************************************************
    // TESTING THE UTILITY FUNCTIONS OF THE REGISTRATION
    // *************************************************

    public function testGettingAllUsersWorks() {
        $users = ScopusOptionsRegistration::allUsers();
        $this->assertEquals(0, count($users));

        // Inserting a user now and then watching if something changes
        $user_id = wp_insert_user(self::DEFAULT_INSERT_USERDATA);
        $this->assertNotEquals(0, $user_id);

        $users = ScopusOptionsRegistration::allUsers();
        $this->assertNotEquals(0, count($users));
        $this->assertEquals(1, count($users));

        $default_user = $users[0];
        $this->assertEquals(self::DEFAULT_INSERT_USERDATA['display_name'], $default_user->display_name);

        // clean up: deleting the user again
        wp_delete_user($user_id);
    }

    public function testGettingAllUserArraysWorks() {
        // inserting the default user
        $user_id = wp_insert_user(self::DEFAULT_INSERT_USERDATA);

        $user_arrays = ScopusOptionsRegistration::allUserArrays();
        $this->assertNotEquals(0, count($user_arrays));

        $default_user_array = $user_arrays[0];
        $this->assertEquals(self::DEFAULT_INSERT_USERDATA['display_name'], $default_user_array['name']);
        $this->assertEquals($user_id, $default_user_array['ID']);

        // clean up: Deleting the created user again
        wp_delete_user($user_id);
    }
}