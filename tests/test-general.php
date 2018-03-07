<?php

/**
 * @group general
 */
class Tests_General extends SearchPress_UnitTestCase {

	function setUp() {
		parent::setUp();

		$this->factory->post->create( array( 'post_title' => 'lorem-ipsum', 'post_date' => '2009-07-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'comment-test', 'post_date' => '2009-08-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'one-trackback', 'post_date' => '2009-09-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'many-trackbacks', 'post_date' => '2009-10-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'no-comments', 'post_date' => '2009-10-02 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'one-comment', 'post_date' => '2009-11-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'contributor-post-approved', 'post_date' => '2009-12-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'embedded-video', 'post_date' => '2010-01-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'simple-markup-test', 'post_date' => '2010-02-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'raw-html-code', 'post_date' => '2010-03-01 00:00:00' ) );

		// Force sync posts.
		SP_Sync_Manager()->sync_posts_cron();

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );
	}

	function test_search_activation() {
		SP_Config()->update_settings( array( 'active' => false ) );
		SP_Integration()->remove_hooks();

		$this->go_to( '/?s=trackback' );
		$this->assertEquals( get_query_var( 's' ), 'trackback' );
		$this->assertEquals( false, strpos( $GLOBALS['wp_query']->request, 'SearchPress' ) );

		SP_Config()->update_settings( array( 'active' => true ) );
		SP_Integration()->init_hooks();
		$this->go_to( '/?s=trackback' );
		$this->assertEquals( get_query_var( 's' ), 'trackback' );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
	}

	function test_settings() {
		$host = getenv( 'SEARCHPRESS_HOST' );
		if ( empty( $host ) ) {
			$host = 'http://localhost:9200';
		}
		SP_Config()->settings = false;
		delete_option( 'sp_settings' );

		$this->assertEquals( 'http://localhost:9200', SP_Config()->host() );
		$this->assertTrue( SP_Config()->must_init() );
		$this->assertFalse( SP_Config()->active() );

		SP_Config()->update_settings( array( 'active' => true, 'must_init' => false, 'host' => $host ) );
		$this->assertEquals( $host, SP_Config()->host() );
		$this->assertFalse( SP_Config()->must_init() );
		$this->assertTrue( SP_Config()->active() );

		SP_Config()->settings = false;
		delete_option( 'sp_settings' );
		SP_Config()->update_settings( array( 'active' => true, 'must_init' => false, 'host' => $host ) );
		$this->assertEquals( $host, SP_Config()->host() );
		$this->assertFalse( SP_Config()->must_init() );
		$this->assertTrue( SP_Config()->active() );
	}
}
