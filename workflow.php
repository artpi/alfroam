<?php


class Roam {
	public $config_file;
	public $output = [];
	public $config = [];
	public $file = '';
	public $graph = '';
	public $modifier = '';
	public $id_indexed = array();
	public $uid_indexed = array();
	public $page_indexed = array();
	public $search;
	function get_fresh_backup() {
		$dir = scandir( $this->config['location'], SCANDIR_SORT_DESCENDING );
		foreach ( $dir as $file ) {
			if( preg_match( '#backup-([a-z\-\_]+)-([0-9\-]+)\.edn#is', $file, $match ) ) {
				$this->graph = $match[1];
				$this->file = $this->config['location'] . '/' . $match[0];
				return true;
			}
		}
		return false;
	}

	function load_backup() {
		$b = $this->get_fresh_backup();
		if ( ! $b ) {
			return false;
		}
		preg_match_all( '#\[([0-9]+) :(block/string|block/parents|edit/time|block/children|block/uid|node/title|block/order) "?(.*?)"? ([0-9]+)\]#is', file_get_contents( $this->file ), $matches );

		for ( $i = 0; $i < count( $matches[0] ); $i++ ) {
			$id = $matches[1][$i];
			if ( ! isset( $this->id_indexed[ $id ] ) ) {
				$this->id_indexed[ $id ] = array(
					'id' => $id,
					'block/parents' => array(),
					'block/children' => array(),
				);
			}

			if ( in_array( $matches[2][ $i ], [ 'block/parents', 'block/children' ] ) ) {
				$this->id_indexed[ $id ][ $matches[2][ $i ] ][] = $matches[3][ $i ];
			} else {
				$this->id_indexed[ $id ][ $matches[2][ $i ] ] = $matches[3][ $i ];
			}

			if ( $matches[2][ $i ] === 'block/uid' ) {
				$this->uid_indexed[ $matches[3][ $i ] ] = $this->id_indexed[ $id ];
			}
			if ( $matches[2][ $i ] === 'node/title' ) {
				$this->page_indexed[ $matches[3][ $i ] ] = $this->id_indexed[ $id ];
			}
		}
		return count( $this->uid_indexed );
	}

	function search( $search, $modifier = '' ) {
		$this->config_file = "{$_SERVER['HOME']}/.config/alfroam.json";
		$this->modifier = $modifier;
		$this->search = $search;
		if ( file_exists( $this->config_file ) ) {
			$this->config = json_decode( file_get_contents( $this->config_file ), true );
		}
		if ( isset( $this->search ) && substr( $this->search, 0, 1 ) === '/' && file_exists( $this->search ) && is_dir( $this->search ) ) {
			$this->config['location'] = $this->search;
			file_put_contents( $this->config_file, json_encode( $this->config ) );
			$this->output[] = array(
				'title' => "Your graph graph backup directory updated",
				'subtitle' => $this->config['location'],
				'arg' => 'https://deliber.at/roam-alfred/',
			);
		} else if ( empty( $this->config['location'] ) || ! $this->load_backup() ) {
			$this->output[] = array(
				'title' => 'Cannot read your database. ',
				'subtitle' => 'Point me to your graph .json file. ex: "roam /Users/me/Desktop/roam"',
				'arg' => 'https://deliber.at/roam-alfred/',
			);
		} else {
			$this->s( $search );
		}
		echo json_encode( array( 'items' => $this->output ) );
	}

	function s( $search ) {
		$this->search = $search;
		if ( ! $this->search ) {
			$this->output = array_map( function( $item ) { return $this->output( $item ); }, array_values( $this->page_indexed ) );
		} else if( preg_match( "#^\[\[([^\]]+)\]\] ?(.*?)$#i", $this->search, $match ) ) {
			if ( isset( $this->page_indexed[ $match[1] ] ) ) {
				$this->output[] = $this->output( $this->page_indexed[ $match[1] ] );
				foreach( $this->page_indexed[ $match[1] ]['block/children'] as $child ) {
					if ( ! $match[2] || stristr( $this->id_indexed[ $child ][ 'block/string' ], $match[2] ) ) {
						$this->output[] = $this->output( $this->id_indexed[ $child ], "\t" );
					}
				}
			}
		} else if ( substr( $this->search, 0, 2 ) === '[[' ) {
			$search = substr( $search, 2 );
			$this->output = array_values(
				array_map(
					function( $item ) {
						return $this->output( $item );
					},
					array_filter(
						array_values( $this->page_indexed ),
						function( $item ) use ( $search ) {
							return stristr( strtolower( $item['node/title'] ), strtolower( $search ) );
						}
					)
				)
			);
		} else if (
			preg_match( "#^\(\(([a-zA-Z0-9\-_]+)\)\) ?(.*?)$#i", $this->search, $match ) &&
			isset( $this->uid_indexed[ $match[1] ] )
		) {
			$item = $this->uid_indexed[ $match[1] ];
			if( isset( $item['block/parents'][0] ) ) {
				$parent = $this->id_indexed[ $item['block/parents'][0] ];
				$this->output[] = $this->output( $parent, '◀️ ' );
			}
			$this->output[] = $this->output( $item, '' );
			foreach( $item['block/children'] as $child ) {
				if ( ! $match[2] || stristr( $this->id_indexed[ $child ][ 'block/string' ], $match[2] ) ) {
					$this->output[] = $this->output( $this->id_indexed[ $child ], "\t" );
				}
			}
		} else {
			// Now we search just in strings.
			$this->output = array_values(
				array_map(
					function( $item ) {
						return $this->output( $item );
					},
					array_filter(
						array_values( $this->id_indexed ),
						function( $item ) use ( $search ) {
							return ! empty( $item['block/string'] ) && stristr( $item['block/string'], $search );
						}
					)
				)
			);
		}
		return $this->output;
	}

	function output( $item, $append = '' ) {
		$out = array(
			'title' => $append,
			'autocomplete' => '',
		);

		if ( isset( $item['block/parents'], $item['block/parents'][0] ) ) {
			$parent = $this->id_indexed[ $item['block/parents'][0] ];
			$out['subtitle'] = "[[{$parent['node/title']}]]";
		} else {
		}
		// TODO: for imported items this may be empty and we may need to search for parent?

		if( isset( $item['block/string'] ) && preg_match( '#::\s*(http\S+)#is', $item['block/string'], $match  ) ) {
			$out['arg'] = $match[1];
		} else if( isset( $item['block/string'] ) && substr( $item['block/string'], 0, 4 ) === 'http' ) {
			$out['arg'] = trim( $item['block/string'] );
		} else if( isset( $item['block/uid'] ) ) {
			$out['arg'] = "https://roamresearch.com/#/app/{$this->graph}/page/{$item['block/uid']}";
		}
		if( isset( $item['block/uid'] ) ) {
			$out['autocomplete'] = "(({$item['block/uid']}))";
		} else if ( isset( $item['node/title'] ) ) {
			$out['autocomplete'] =  "[[{$item['node/title']}]]";
		}
		if( isset( $item['block/string'] ) ) {
			$out['title'] .= $item['block/string'];
			$out['text'] = array(
				'largetype' => $item['block/string'],
				'copy' => $item['block/string'],
			);
			$out['mods'] = array(
			    "cmd" => [
			        "valid" => true,
			        "arg" => $item['block/string'],
			        "Copy and Paste: " =>  $item['block/string'],
			    ],
			);
		} else if ( isset( $item['node/title'] ) ) {
			$out['title'] .= "[[{$item['node/title']}]]";
		}
		return $out;
	}
}
