<?php


class Roam {
	static $config_file = "./.config.json";
	public $output = [];
	public $config = null;
	public $argv;
	function search( $argv ) {
		$this->argv = $argv;
		if(  isset($argv[1]) && substr( $argv[1], 0, 1 ) === '/' && substr( $argv[1], -5, 5 ) === '.json' && file_exists( $argv[1] ) && preg_match( '#/([^/.]+)\.json#i', $argv[1], $match ) ) {
			$this->config = (object) [
				'graph' => $match[1],
				'location' => $argv[1],
			];
			file_put_contents( self::$config_file, json_encode( $this->config ) );
			$this->output[] = array(
				'title' => "Your graph {$this->config->graph} updated",
				'subtitle' => $this->config->location,
				'arg' => 'https://piszek.com',
			);


		}


		if( file_exists( self::$config_file ) ) {
			$this->config = json_decode( file_get_contents( self::$config_file ) );
			if( file_exists( $this->config->location ) ) {
				$json = file_get_contents( $this->config->location );
				$data = json_decode( $json );
			}
		}


		if( $data ) {
			foreach ( $data  as $page ) {
				$this->explore( $page, $page->title, null );
			}
		} else {
			$this->output[] = array(
				'title' => 'Cannot read your database. ',
				'subtitle' => 'Point me to your graph .json file. ex: "roam /Users/me/Desktop/me.json"',
				'arg' => 'https://piszek.com',
			);
		}
		echo json_encode( array( 'items' => $this->output ) );
	}

	function output( $item, $pagetitle = '', $append = '' ) {
		$out = array(
			'title' => $append,
			'autocomplete' => '',
		);

		if( $pagetitle ) {
			$out['subtitle'] = "[[$pagetitle]]";
		}

		if( isset( $item->string ) && substr( $item->string, 0, 4 ) === 'http' ) {
			$out['arg'] = trim( $item->string );
		} else if( isset( $item->uid ) ) {
			$out['arg'] = "https://roamresearch.com/#/app/{$this->config->graph}/page/{$item->uid}";
		}
		if( isset( $item->uid ) ) {
			$out['autocomplete'] = "(({$item->uid}))";
		} else if ( isset( $item->title ) ) {
			$out['autocomplete'] =  "[[$item->title]]";
		}
		if( isset( $item->string ) ) {
			$out['title'] .= trim( substr( $item->string, 0, 100 ) );
			$out['text'] = array(
				'largetype' => $item->string,
				'copy' => $item->string,
			);
		} else if ( isset( $item->title ) ) {
			$out['title'] .= "[[$item->title]]";
		}

		$this->output[] = $out;
	}

	function explore( $data, $title, $parent ) {
		// We add a block
		if( ! isset( $this->argv[1] ) ) {
			if( isset( $data->title ) ) {
				$this->output( $data, $title );
			}
		} else if( isset( $data->title ) && preg_match( "#\[\[([^\]]+)\]\]#i", $this->argv[1], $match ) && (strtolower($data->title) === strtolower($match[1]) ) ) {
			$this->output( $data );
		} else if( isset( $data->uid ) && strtolower( trim( $this->argv[1] ) ) === '((' . strtolower( $data->uid ) . '))'  ) {
			if( $parent ) {
				$this->output( $parent, $title, "◀️ " );
			}
			$this->output( $data, $title, "\t" );
		} else if ( isset( $parent->title ) && strtolower( $this->argv[1] ) === strtolower( "[[$parent->title]]" )  ) {
			$this->output( $data, $title, "\t" );
		} else if ( isset( $parent->uid ) && strtolower( trim( $this->argv[1] ) ) === '((' . strtolower( $parent->uid ) . '))'   ) {
			$this->output( $data, $title, "\t" );
		} else if ( substr( $this->argv[1], 0, 2 ) === '[[' ) {
			if( isset( $data->title ) && stristr( $data->title, substr( $this->argv[1], 2 ) ) ) {
				$this->output( $data, $title );
			}
			return;
		} else if ( isset( $data->string ) && stristr( $data->string, $this->argv[1] ) ) {
			$this->output( $data, $title );
		}
		if( isset( $data->children ) ) {
			foreach ( $data->children  as $block ) {
				$this->explore( $block, $title, $data );
			}
		}

	}
}






