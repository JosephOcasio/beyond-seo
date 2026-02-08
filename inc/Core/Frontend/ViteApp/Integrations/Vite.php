<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;

class Vite {
    /**
     * @action wp_head 1
     */
    public function client(): void {
        printf( '<script type="module" defer src="%s"></script>', esc_attr( ReactApp::get()->config()->get( 'hmr.client' ) ) );
    }

    /**
     * @filter rc_assets_resolver_url 1 2
     */
    public function url( string $url, string $path ): string {
		if ($url) {
			return $url;
		}
		return ReactApp::get()->config()->get( 'hmr.sources' ) . "/{$path}";
	}
}
