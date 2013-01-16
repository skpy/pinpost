<?php
class pinpost extends Plugin
{
	public function action_form_user( $form, $edit_user )
	{
		$pinpost = $form->insert( 'page_controls', 'wrapper', 'pinpost', _t( 'PinPost', 'pinpost' ) );
		$pinpost->class = 'container settings';
		$pinpost->append( 'static', 'pinpost', '<h2>' . htmlentities( _t( 'PinPost', 'pinpost' ), ENT_COMPAT, 'UTF-8' ) . '</h2>' );

		$username = $form->pinpost->append( 'text', 'pinpost_username', 'null:null', _t('Your Pinboard username: ', 'pinpost' ), 'optionscontrol_text' );
		$username->class[] = 'item clear';
		$username->charlimit = 50;
		$username->value = $edit_user->info->pinpost_username;

		$password = $form->pinpost->append( 'text', 'pinpost_password', 'null:null', _t( 'Your Pinboard password: ', 'pinpost' ), 'optionscontrol_text' );
		$password->class[] = 'item clear';
		$password->charlimit = 50;
		$password->value = $edit_user->info->pinpost_password; 

		$pintag = $form->pinpost->append( 'text', 'pinpost_pintag', 'null:null', _t( 'Only fetch Pinboard items with this tag: ', 'pinpost' ), 'optionscontrol_text' );
		$pintag->class[] = 'item clear';
		$pintag->charlimit = 50;
		$pintag->value = $edit_user->info->pinpost_pintag;

		$pintitle = $form->pinpost->append( 'text', 'pinpost_title', 'null:null', _t( 'Title to use for new PinPost posts: ', 'pinpost' ), 'optionscontrol_text' );
		$pintitle->class[] = 'item clear';
		$pintitle->charlimit = 50;
		$pintitle->value = $edit_user->info->pinpost_title;

		$posttag = $form->pinpost->append( 'text', 'pinpost_posttag', 'null:null', _t( 'Tag to assign to posts created from Pinboard items: ', 'pinpost' ), 'optionscontrol_text' );
		$posttag->class[] = 'item clear';
		$posttag->charlimit = 50;
		$posttag->value = $edit_user->info->pinpost_posttag;

		$listtype = $form->pinpost->append( 'select', 'pinpost_listtype', 'null:null', _t( 'List type to use for Pinboard items: ', 'pinpost' ) );
		$listtype->class[] = 'item clear';
		$listtype->options = array( 'ol' => 'Ordered', 'ul' => 'Unordered', 'none' => 'None' );
		$listtype->template = 'optionscontrol_select';
		$listtype->value = $edit_user->info->pinpost_listtype;
	}

	public function filter_adminhandler_post_user_fields( $fields )
	{
		$fields['pinpost_username'] = 'pinpost_username';
		$fields['pinpost_password'] = 'pinpost_password';
		$fields['pinpost_pintag']   = 'pinpost_pintag';
		$fields['pinpost_title']    = 'pinpost_title';
		$fields['pinpost_posttag']  = 'pinpost_posttag';
		$fields['pinpost_listtype'] = 'pinpost_listtype';
		return $fields;
	}

	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[] = _t( 'Test' );
		}
		return $actions;
	}

	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ( $action ) {
				case _t( 'Test' ):
					$this->get( User::identify() );
						Utils::redirect( URL::get( 'admin', 'page=plugins' ) );
						break;
			}
		}	
	}

	public function get( $user )
	{
		include_once 'pinboard-api.php';
		$p = new PinboardAPI ( $user->info->pinpost_username, $user->info->pinpost_password  );
		$bookmarks = $p->get_all( null, null, $user->info->pinpost_pintag, $user->info->pinpost_lastcheck );
		if ( ! $bookmarks ) {
			return;
		}

		$open = '';
		$close = '';
		$itemopen = '';
		$itemclose = '<br />';
		if ( 'none' != $user->info->pinpost_listtype ) {
			$open = '<' . $user->info->pinpost_listtype . '>';
			$close = '</' . $user->info->pinpost_listtype . '>';
			$itemopen = '<li>';
			$itemclose = '</li>';
		}
		$post = Post::get( array(
			'user_id'   => $user->id,
			'status'    => Post::status('draft'),
			'vocabulary' => array ( 'tags:term' => $user->info->pinpost_posttag ),
			) );
		if ( ! $post ) {
			// no draft post exists, let's prep a new one
			$content = '<p>' . $open . $close . '</p>';
			$postdata = array(
				'title'        => $user->info->pinpost_title,
				'tags'         => $user->info->pinpost_posttag,
				'content'      => $content,
				'user_id'      => $user->id,
				'content_type' => Post::type( 'entry' ),
				'status'       => Post::status('draft'),
			);
			$post = Post::create( $postdata );
		}
		$content = '';
		foreach ( $bookmarks as $bookmark ) {
			$content .= $itemopen . '<h3><a href="' . $bookmark->url . '">' . $bookmark->title . '</a></h3><p>' . $bookmark->description . '</p>' . $itemclose;
		}
		$content .= $close . '</p>';
		$post->content = str_replace( "$close</p>", $content, $post->content );
		$post->update();
		$user->info->pinpost_lastcheck = time();
		$user->update();
	}
}
?>