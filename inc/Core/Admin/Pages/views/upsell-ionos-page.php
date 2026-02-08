<?php
/**
 * IONOS-Specific Upsell Page View Template
 * 
 * This is a view file that contains the complete HTML/CSS template for the IONOS upsell page.
 * It expects variables to be passed from the parent scope.
 * 
 * Expected Variables:
 * @var string $headerBgColor - Background color for the header section
 * @var string $logoUrl - URL to the logo image
 * @var array $currentPageContent - Array containing title, description, faqTitle, and faqs
 * @var array $plans - Array of plan configurations with pricing and features
 * @var array $featuredCards - Array of feature cards to display
 * @var array $proSections - Array of pro upgrade sections with cards
 * @var array $faqSection - Array containing FAQ title and items
 * @var int $currentPlanLevel - Current subscription level (0=Free, 1=Standard, 2=Advanced, 3=Social)
 * 
 * @package RankingCoach
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// IONOS-specific data preparation
$currentPlanLevel = \RankingCoach\Inc\Core\Helpers\CoreHelper::getCurrentPlanLevel();
$headerBgColor = '#00429B';
$logoUrl = plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/icons/upsell-logo-small.png';

// Page content definitions
$pageContent = [
	'standard' => [
		'title' => __('Freemium got your business online. But other businesses pay to stand out first.', 'beyond-seo'),
		'description' => __("You're fighting for attention against companies that automate their work while you're still handling things manually. We offer more than one plan to help you change that. Choose the one that fits you best to save time, stay ahead, and grow faster.", 'beyond-seo'),
		'faqTitle' => __('FAQs', 'beyond-seo'),
		'faqs' => [
			[
				'question' => __('1. Is the Standard plan suitable for small businesses?', 'beyond-seo'),
				'answer' => __('Yes. The Standard plan is designed for small businesses looking to strengthen their visibility and presence.', 'beyond-seo')
			],
			[
				'question' => __('2. Can I directly upgrade to Advanced?', 'beyond-seo'),
				'answer' => __('Yes. You can upgrade to the Advanced plan at any time and gain immediate access to all additional features and benefits.', 'beyond-seo')
			],
			[
				'question' => __('3. Can I cancel my plan?', 'beyond-seo'),
				'answer' => __('Yes. You may cancel your plan whenever you choose.', 'beyond-seo')
			]
		]
	],
	'advanced' => [
		'title' => __('Trust is the most important factor in a purchase. Advanced helps you get trusted by clients.', 'beyond-seo'),
		'description' => __('Customers trust what others say online about your business. With AI-powered features, you can reply to reviews right away, get more customers to share their feedback, and learn what people really think with sentiment analysis. All these features and more work together to help you build trust and stand out online.', 'beyond-seo'),
		'faqTitle' => __('FAQs', 'beyond-seo'),
		'faqs' => [
			[
				'question' => __('1. Who is Advanced best suited for?', 'beyond-seo'),
				'answer' => __('Businesses in competitive markets, those managing multiple review sources, or anyone who needs broader listings coverage and deeper reputation insights.', 'beyond-seo')
			],
			[
				'question' => __('2. How can I buy the Social add-on?', 'beyond-seo'),
				'answer' => __("To buy the Social add-on, you first need to have the Advanced plan. Once you've upgraded to Advanced, you'll be able to purchase the add-on separately.", 'beyond-seo')
			],
			[
				'question' => __('3. Can I cancel my plan?', 'beyond-seo'),
				'answer' => __('Yes. You may cancel your plan whenever you choose.', 'beyond-seo')
			]
		]
	],
	'social' => [
		'title' => __('60% of businesses that grew last year relied on social media. Use the social add-on to join this year`s growing businesses.', 'beyond-seo'),
		'description' => __('Show up where your clients are, share content that captures attention, and keep the conversation going. Plan, post, and boost to build trust, grow engagement, and turn followers into loyal customers', 'beyond-seo'),
		'faqTitle' => __('FAQs', 'beyond-seo'),
		'faqs' => [
			[
				'question' => __('1. Is the Social add-on right for my business?', 'beyond-seo'),
				'answer' => __("It's ideal for businesses that want to actively engage their audience, grow visibility on social media, and streamline posting with AI and planning features.", 'beyond-seo')
			],
			[
				'question' => __('2. Can I track performance and engagement?', 'beyond-seo'),
				'answer' => __('Yes. You can monitor post interactions, view and reply to comments, track link performance, and access social media insights.', 'beyond-seo')
			],
			[
				'question' => __('3. Can I create and publish posts on all major platforms?', 'beyond-seo'),
				'answer' => __('Yes. You can create and publish posts on Google Business Profile, Facebook, Instagram, LinkedIn, and X.', 'beyond-seo')
			]
		]
	],
	'allUnlocked' => [
		'title' => __('60% of businesses that grew last year relied on social media. Use the social add-on to join this year`s growing businesses.', 'beyond-seo'),
		'description' => __('Show up where your clients are, share content that captures attention, and keep the conversation going. Plan, post, and boost to build trust, grow engagement, and turn followers into loyal customers', 'beyond-seo'),
		'faqTitle' => __('FAQs', 'beyond-seo'),
		'faqs' => []
	]
];

// Plans definitions
$plans = [
	// STANDARD
	'standard' => [
		'plan' => 'seo_ai_small',
		'name' => __('Standard', 'beyond-seo'),
		'price' => '', // hidden on UI
		'period' => '',
		'isCurrent' => false,
		'isRecommended' => false,
		'features' => [
			'<strong>'.__('Everything in Freemium, plus:', 'beyond-seo').'</strong>',
			__('AI powered SEO tasks', 'beyond-seo'),
			__('AI text optimisation', 'beyond-seo'),
			__('AI URL optimiser', 'beyond-seo'),
			__('Create Google ads campaign', 'beyond-seo'),
			__('Monitor 3 locations', 'beyond-seo'),
			__('SEO tasks', 'beyond-seo'),
			__('Social Media tasks', 'beyond-seo'),
			__('Local Marketing tasks', 'beyond-seo'),
		],
		'upgradeButton' => [
			'show' => true,
			'text' => __('Proceed to shop', 'beyond-seo'),
			'link' => admin_url('admin.php?page=rankingcoach-upsell&step=upsell&planSelected=seo_ai_small'),
		],
		'hasTermsAndConditions' => false,
		'termsLink' => '',
		'privacyLink' => '',
	],
	// ADVANCED (Recommended)
	'advanced' => [
		'plan' => 'seo_ai_medium2025',
		'name' => __('Advanced', 'beyond-seo'),
		'price' => '', // hidden on UI
		'period' => '',
		'isCurrent' => false,
		'isRecommended' => true,
		'features' => [
			'<strong>' . __('Everything in Standard, plus:', 'beyond-seo') . '</strong>',
			__('Publish Business Profile in up to 46 directories', 'beyond-seo'),
			__('Automatic AI reply to reviews', 'beyond-seo'),
			__('Reviews booster (send review request via e-mail or use print materials)', 'beyond-seo'),
			__('AI-powered reputation sentiment analysis', 'beyond-seo'),
			__('Track 25 keywords', 'beyond-seo'),
			__('Monitor 5 competitors', 'beyond-seo'),
			__('Online Presence insights', 'beyond-seo'),
			__('Manually reply to reviews', 'beyond-seo'),
			__('Generate replies to reviews with AI', 'beyond-seo'),
			__('Collect reviews widget', 'beyond-seo'),
			__('Add additional review sources', 'beyond-seo'),
			__('Track online mentions', 'beyond-seo'),
			__('Reputation insights', 'beyond-seo'),
		],
		'upgradeButton' => [
			'show' => true,
			'text' => __('Proceed to shop', 'beyond-seo'),
			'link' => admin_url('admin.php?page=rankingcoach-upsell&step=upsell&planSelected=seo_ai_medium2025'),
		],
		'hasTermsAndConditions' => false,
		'termsLink' => '',
		'privacyLink' => '',
	],
	// SOCIAL (Add-on)
	'social' => [
		'plan' => 'seo_ai_social',
		'name' => __('Social', 'beyond-seo'),
		'price' => '', // hidden on UI
		'period' => '',
		'isCurrent' => false,
		'isRecommended' => true,
		'features' => [
			'<strong>'.__('Everything in Advanced, plus:', 'beyond-seo').'</strong>',
			__('Create & publish posts on Facebook, Instagram, LinkedIn, X and Google Business Profile', 'beyond-seo'),
			__('AI-powered Social Media Planner', 'beyond-seo'),
			__('AI content for posts and events', 'beyond-seo'),
			__('AI image generator', 'beyond-seo'),
			__('Create & publish events on Google Business Profile', 'beyond-seo'),
			__('Competitor-based post suggestion', 'beyond-seo'),
			__('Videos on posts', 'beyond-seo'),
			__('View & respond to posts comments', 'beyond-seo'),
			__('URL shortener', 'beyond-seo'),
			__('Link performance', 'beyond-seo'),
			__('Social media insights', 'beyond-seo'),
			__('Boosts posts with Meta ads', 'beyond-seo'),
		],
		'upgradeButton' => [
			'show' => true,
			'text' => __('Proceed to shop', 'beyond-seo'),
			'link' => admin_url('admin.php?page=rankingcoach-upsell&step=upsell&planSelected=seo_ai_social'),
		],
		'hasTermsAndConditions' => false,
		'termsLink' => '',
		'privacyLink' => '',
	],
];

// Current page content selection
$currentPageContent = match ($currentPlanLevel) {
	0 => $pageContent['standard'],
	1 => $pageContent['advanced'],
	2 => $pageContent['social'],
	default => $pageContent['allUnlocked']
};

// Feature cards
$allFeatureCards = [
	'grow-your-business' => [
		'title' => __('Grow your business online with easy video tutorials.', 'beyond-seo'),
		'description' => __('Follow simple step-by-step videos for SEO, Local Marketing, and Social Media tasks. Get results faster without expert help. Save time and skip the complexity of doing it all yourself.', 'beyond-seo'),
		'image' => 'assets/svg/grow-your-business.svg'
	],
	'reach-more-clients' => [
		'title' => __('Reach more clients with ads. Right where they are.', 'beyond-seo'),
		'description' => __('Create your Google Ads campaign in minutes. Choose from automatically generated ad texts, set your daily budget, and launch with ease. Once your campaign is live, monitor its performance with insights. You can adjust or pause it anytime to stay in control.', 'beyond-seo'),
		'image' => 'assets/svg/reach-more-clients.svg'
	],
	'get-found-faster' => [
		'title' => __('Get found faster with AI-powered text optimisation.', 'beyond-seo'),
		'description' => __('AI reviews your content, fixes SEO issues, and fine-tunes it for your keywords. This helps your business rank higher and reach the right audience with less effort.', 'beyond-seo'),
		'image' => 'assets/svg/get-found-faster.svg'
	],
	'stay-on-top' => [
		'title' => __('Stay on top of every review. Respond in no time.', 'beyond-seo'),
		'description' => __('Reply with AI or manually to reviews from Google, Facebook, and numerous other sources, in one single place. All to save time and protect your reputation.', 'beyond-seo'),
		'image' => 'assets/svg/stay-on-top.svg'
	],
	'increase-your-digital' => [
		'title' => __('Increase your digital touch points. Get found easily.', 'beyond-seo'),
		'description' => __('Publish your Business Profile in up to 46 directories with one click. Monitor your overall listings presence and manage it easily with fine-tune updates.', 'beyond-seo'),
		'image' => 'assets/svg/increase-your-digital.svg'
	],
	'create-social-media' => [
		'title' => __('Create social media content with ease.', 'beyond-seo'),
		'description' => __('Create posts and events, fine-tune them with AI assistance. Schedule or publish them across multiple social media channels, adapted for each platform.', 'beyond-seo'),
		'image' => 'assets/svg/create-social-media.svg'
	],
	'transform-one-content' => [
		'title' => __('Transform one content idea into many ready-to-use posts.', 'beyond-seo'),
		'description' => __("Planning a promotion, and one post won't cut it? Simply tell the AI what the series is about, the number of posts, frequency, and schedule. The AI will generate your entire series in seconds. Review, tweak if needed, and your posts will be published automatically as scheduled.", 'beyond-seo'),
		'image' => 'assets/svg/transform-one-content.svg'
	],
	'make-it-easy' => [
		'title' => __('Make it easy for customers to leave reviews.', 'beyond-seo'),
		'description' => __('Encourage more customers to leave feedback by sending review requests via email or offering printed QR materials for easy scanning at your location.', 'beyond-seo'),
		'image' => 'assets/svg/make-it-easy.svg'
	],
	'see-what-customers' => [
		'title' => __('See what customers really think of your business.', 'beyond-seo'),
		'description' => __("Get a full overview of your business reputation based on customer reviews. See an overall sentiment score and a detailed breakdown by topic, showing what customers value most and where there's room for improvement.", 'beyond-seo'),
		'image' => 'assets/svg/see-what-customers.svg'
	],
	'creating-buzz-means' => [
		'title' => __('Creating buzz means having visuals without a design team.', 'beyond-seo'),
		'description' => __('Skip complicated design tools. Just describe the image you want or select relevant keywords, and our AI will generate ready-to-use visuals in seconds.', 'beyond-seo'),
		'image' => 'assets/svg/creating-buzz-means.svg'
	],
	'expand-your-reach' => [
		'title' => __('Expand your reach. Boost your posts with Meta ads.', 'beyond-seo'),
		'description' => __("Choose a post to boost, set your budget, and launch instantly. Once your boost is live, track its performance with detailed insights. You're always in control, pause or adjust your boost and budget anytime.", 'beyond-seo'),
		'image' => 'assets/svg/expand-your-reach.svg'
	],
];

// Plan feature configuration
$planFeatureConfig = [
	0 => [ // Free plan - show Standard features
		'featureCards' => ['grow-your-business', 'reach-more-clients', 'get-found-faster'],
		'proSections' => [
			[
				'highlight' => __('Advanced', 'beyond-seo'),
				'title' => __('takes it further', 'beyond-seo'),
				'cards' => ['stay-on-top', 'increase-your-digital']
			],
			[
				'highlight' => __('Social', 'beyond-seo'),
				'title' => __('completes the picture', 'beyond-seo'),
				'cards' => ['create-social-media', 'transform-one-content']
			]
		]
	],
	1 => [ // Standard plan - show Advanced features
		'featureCards' => ['stay-on-top', 'make-it-easy', 'increase-your-digital', 'see-what-customers'],
		'proSections' => [
			[
				'highlight' => __('Social', 'beyond-seo'),
				'title' => __('completes the picture', 'beyond-seo'),
				'cards' => ['create-social-media', 'transform-one-content']
			]
		]
	],
	2 => [ // Advanced plan - show Social features
		'featureCards' => ['transform-one-content', 'creating-buzz-means', 'create-social-media', 'expand-your-reach'],
		'proSections' => []
	],
	3 => [ // Social/Pro plan - all features unlocked, no pro sections but show all cards
		'featureCards' => array_keys($allFeatureCards),
		'proSections' => [] // No upsell needed
	]
];

// Build current configuration
$currentPlanConfig = $planFeatureConfig[$currentPlanLevel] ?? $planFeatureConfig[0];

// Build featured cards array
$featuredCards = [];
$cardIndex = 0;
foreach ($currentPlanConfig['featureCards'] as $cardKey) {
	if (isset($allFeatureCards[$cardKey])) {
		$card = $allFeatureCards[$cardKey];
		$card['layout_class'] = ($cardIndex % 2 === 0) ? 'image-left' : 'image-right';
		$featuredCards[] = $card;
		$cardIndex++;
	}
}

// Build pro sections array
$proSections = [];
foreach ($currentPlanConfig['proSections'] as $proSection) {
	$proCards = [];
	$proCardIndex = 0;
	foreach ($proSection['cards'] as $cardKey) {
		if (isset($allFeatureCards[$cardKey])) {
			$card = $allFeatureCards[$cardKey];
			$card['layout_class'] = ($proCardIndex % 2 === 0) ? 'image-left' : 'image-right';
			$proCards[] = $card;
			$proCardIndex++;
		}
	}
	if (!empty($proCards)) {
		$proSections[] = [
			'highlight' => $proSection['highlight'],
			'title' => $proSection['title'],
			'cards' => $proCards
		];
	}
}

// FAQ section
$faqSection = [
	'title' => $currentPageContent['faqTitle'] ?? '',
	'items' => $currentPageContent['faqs'] ?? []
];

?>
<div class='wrap rankingcoach-upsell-page'>
    <div class='rankingcoach-upsell-header' style="background-color: <?php echo esc_attr($headerBgColor); ?>;">
        <div class='rankingcoach-upsell-logo'>
            <img src="<?php echo esc_url($logoUrl); ?>" alt='RankingCoach Logo'>
        </div>
    </div>

    <div class='rankingcoach-upsell-content'>
        <h2 class='rankingcoach-upsell-title'><?php echo esc_html($currentPageContent['title']); ?></h2>

        <p class="rankingcoach-upsell-description">
            <?php echo esc_html($currentPageContent['description']); ?>
        </p>

        <?php
        // Display current plan message for all subscription levels
        $currentPlanDisplayName = '';
        switch ($currentPlanLevel) {
            case 0:
                $currentPlanDisplayName = __('Free', 'beyond-seo');
                break;
            case 1:
                $currentPlanDisplayName = __('Standard', 'beyond-seo');
                break;
            case 2:
                $currentPlanDisplayName = __('Advanced', 'beyond-seo');
                break;
            case 3:
                $currentPlanDisplayName = __('Social', 'beyond-seo');
                break;
        }
        if (!empty($currentPlanDisplayName)) : ?>
            <div class="rankingcoach-free-plan-message">
                <?php echo sprintf(
                    /* translators: %s is the plan name (e.g., Free, Standard, Advanced, Pro) */
                        esc_html__('You\'re currently using the %s plan', 'beyond-seo'),
                    '<strong>' . esc_html($currentPlanDisplayName) . '</strong>'
                ); ?>
            </div>
        <?php endif; ?>

        <div class="rankingcoach-pricing-plans">
                <?php if (empty($plans)) : ?>
                <div class="rankingcoach-no-plans">
                    <p><?php echo esc_html__('No plans available at the moment. Please try again later.', 'beyond-seo'); ?></p>
                </div>
            <?php else :
                // Dynamic plans display
                $planKeys = array_keys($plans);
                foreach ($planKeys as $index => $key) :
                    $plan = $plans[$key];

                    // Determine if this specific plan in the loop is the user's current plan
                    $isThisPlanCurrent = ($index + 1 === $currentPlanLevel); // Add +1 since array keys now start at 0 but levels start at 1

                    // Determine if this plan should be greyed out
                    $shouldBeGreyedOut = ($index + 1) < $currentPlanLevel;

                    // Skip rendering cards at or below the current plan level
                    if (($index + 1) <= $currentPlanLevel) {
                        continue;
                    }

                    // Update the 'isCurrent' flag in the plan data
                    $plan['isCurrent'] = $isThisPlanCurrent;

                    // Set upgrade button visibility
                    if (isset($plan['upgradeButton'])) {
                        $plan['upgradeButton']['show'] = !$isThisPlanCurrent && !$shouldBeGreyedOut;
                    }

                    // Determine CSS classes for the plan
                    $planClasses = [];
                    if ($isThisPlanCurrent) {
                        $planClasses[] = 'current-plan';
                    }
                    if ($shouldBeGreyedOut) {
                        $planClasses[] = 'lower-than-current';
                    }
                    if (isset($plan['isRecommended']) && $plan['isRecommended']) {
                        $planClasses[] = 'recommended-plan';
                    }
                    // Add special class for Social plan when user is not Advanced
                    if ($key === 'social' && $currentPlanLevel < 2) {
                        $planClasses[] = 'social-not-advanced';
                    }
            ?>
                <div class="rankingcoach-plan <?php echo esc_attr(implode(' ', $planClasses)); ?>">

                    <?php if (isset($plan['isRecommended']) && $plan['isRecommended']) :
                        // Display 'Add-on' for Social plan, 'Recommended' for Advanced plan
                        $badgeText = ($key === 'social') ? __('Add-on', 'beyond-seo') : __('Recommended', 'beyond-seo');
                    ?>
                        <div class="recommended-badge"><?php echo esc_html($badgeText); ?></div>
                    <?php endif; ?>								<div class="plan-header <?php echo esc_attr($shouldBeGreyedOut ? 'greyed-out' : ''); ?>">
                            <h3 class="plan-name"><?php echo esc_html($plan['name'] ?? ''); ?></h3>
                        </div>

                        <div class="plan-pricing">
                            <span class="plan-price <?php echo esc_attr(($shouldBeGreyedOut ? 'greyed-out-text' : '')); ?>">
                                <?php echo wp_kses($plan['price'] ?? '', array('span' => array('class' => array()))); ?>
                            </span>
                            <span class="plan-period <?php echo esc_attr(($shouldBeGreyedOut ? 'greyed-out-text' : '')); ?>">
                                <?php echo esc_html($plan['period'] ?? ''); ?>
                            </span>
                        </div>

                    <?php if ($plan['isCurrent']) : // Relies on $plan['isCurrent'] being correctly set above ?>
                            <div class="current-plan-label" style="color: #B6C1D3; font-weight: bold; text-align: center; padding: 10px 0; font-size: 22px;"><?php esc_html_e('Your current plan', 'beyond-seo'); ?></div>
                        <?php endif; ?>

                        <?php if (isset($plan['upgradeButton']) && isset($plan['upgradeButton']['show']) && $plan['upgradeButton']['show']) : ?>
                            <?php if ($key === 'social' && $currentPlanLevel < 2) : ?>
                                <!-- Show upgrade message for Social when user hasn't reached Advanced -->
                                <div class="plan-cta">
                                    <p class="social-upgrade-message" style="color: #FFAA00; font-weight: 500; text-align: left; margin: 0; font-size: 16px; line-height: 1.5;">
                                        <?php esc_html_e('To buy the add-on, you must upgrade to Advanced first', 'beyond-seo'); ?>
                                    </p>
                                </div>
                            <?php else : ?>
                                <!-- Show normal upgrade button -->
                                <div class="plan-cta">
                                    <a href="<?php echo esc_url($plan['upgradeButton']['link'] ?? '#'); ?>" class="button button-primary upgrade-button">
                                        <?php echo esc_html($plan['upgradeButton']['text'] ?? __('Upgrade', 'beyond-seo')); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>								<div class="plan-features">
                            <ul>
                                <?php if (isset($plan['features']) && is_array($plan['features'])) : ?>
                                    <?php
                                    $totalFeatures = count($plan['features']);
                                    $visibleFeatures = 5;
                                    ?>
                                    <?php foreach ($plan['features'] as $index => $feature) : ?>
                                        <li class="<?php echo esc_attr(($shouldBeGreyedOut ? 'greyed-out-text' : '')); ?> <?php echo ($index >= $visibleFeatures) ? 'feature-hidden' : ''; ?>">
                                            <?php if (strpos($feature, '<strong>') !== false) : ?>
                                                <?php echo wp_kses($feature, ['strong' => []]); ?>
                                            <?php else : ?>
                                                <span class="checkmark <?php echo esc_attr(($shouldBeGreyedOut ? 'greyed-out-checkmark' : '')); ?>">✓</span>
                                                <?php echo esc_html($feature); ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <?php if (isset($plan['features']) && is_array($plan['features']) && count($plan['features']) > 4) : ?>
                                <div class="show-more-container">
                                    <button class="show-more-btn" type="button">
                                        <span class="show-more-icon">▼</span>
                                        <span class="show-more-text"><?php esc_html_e('Show more', 'beyond-seo'); ?></span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Feature Cards Section -->
        <div class="rankingcoach-feature-cards">
            <?php if (!empty($featuredCards)) : ?>
                <?php foreach ($featuredCards as $index => $card) : ?>
                    <div class="feature-card <?php echo esc_attr($card['layout_class']); ?>">
                        <div class="feature-content">
                            <h3 class="feature-title"><?php echo esc_html($card['title'] ?? ''); ?></h3>
                            <p class="feature-description">
                                <?php echo esc_html($card['description'] ?? ''); ?>
                            </p>
                        </div>
                        <div class="feature-image">
                            <img src="<?php echo esc_url(plugin_dir_url( dirname( __DIR__ ) ) . ($card['image'] ?? '')); ?>" alt="<?php echo esc_attr($card['title'] ?? ''); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- End of rankingcoach-feature-cards -->

    <?php foreach ($proSections as $proSection): ?>
        <!-- Pro section -->
        <div class="rankingcoach-pro-section">
            <h2 class="pro-title">
                <span class="pro-highlight"><?php echo esc_html($proSection['highlight']); ?></span>
                <?php echo esc_html($proSection['title']); ?>
            </h2>
        </div>
        <!-- Pro Feature Cards Section -->
        <div class="rankingcoach-pro-feature-cards">
            <?php if (!empty($proSection['cards'])) : ?>
                <?php foreach ($proSection['cards'] as $card) : ?>
                    <div class="feature-card <?php echo esc_attr($card['layout_class']); ?>">
                        <div class="feature-content">
                            <h3 class="feature-title"><?php echo esc_html($card['title']); ?></h3>
                            <p class="feature-description">
                                <?php echo esc_html($card['description']); ?>
                            </p>
                        </div>
                        <div class="feature-image">
                            <img src="<?php echo esc_url(plugin_dir_url( dirname( __DIR__ ) ) . ($card['image'] ?? '')); ?>" alt="<?php echo esc_attr($card['title'] ?? ''); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- End of rankingcoach-pro-feature-cards -->
    <?php endforeach; ?>

        <!-- FAQ Section -->
        <div class="rankingcoach-faq-section">
            <h2 class="faq-section-title"><?php echo esc_html($faqSection['title']); ?></h2>
            <div class="faq-items-container">
                <?php if (!empty($faqSection['items'])) : ?>
                    <?php foreach ($faqSection['items'] as $faq) : ?>
                        <div class="faq-item">
                            <h3 class="faq-question"><?php echo esc_html($faq['question']); ?></h3>
                            <p class="faq-answer"><?php echo esc_html($faq['answer']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- End of rankingcoach-faq-section -->
    </div>

    <style>
        /* Font Face Declarations */
        @font-face {
            font-family: 'OpenSans';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/OpenSans-Regular.ttf'); ?>') format('truetype');
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: 'OpenSans';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/OpenSans-Light.ttf'); ?>') format('truetype');
            font-weight: 300;
            font-style: normal;
        }

        @font-face {
            font-family: 'OpenSans';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/OpenSans-Semibold.ttf'); ?>') format('truetype');
            font-weight: 600;
            font-style: normal;
        }

        @font-face {
            font-family: 'OpenSans';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/OpenSans-Bold.ttf'); ?>') format('truetype');
            font-weight: 700;
            font-style: normal;
        }

        @font-face {
            font-family: 'Overpass';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/Overpass-Regular.ttf'); ?>') format('truetype');
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: 'Overpass';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/Overpass-Light.ttf'); ?>') format('truetype');
            font-weight: 300;
            font-style: normal;
        }

        @font-face {
            font-family: 'Overpass';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/Overpass-Bold.ttf'); ?>') format('truetype');
            font-weight: 700;
            font-style: normal;
        }

        @font-face {
            font-family: 'Overpass';
            src: url('<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/fonts/Overpass-ExtraBold.ttf'); ?>') format('truetype');
            font-weight: 800;
            font-style: normal;
        }

        body {
            background: white;
            font-family: 'OpenSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
        }
        .rankingcoach-upsell-page {
            max-width: none;
            margin: -20px -20px 0 -20px;
            background-color: white;
        }

        .rankingcoach-upsell-header {
            width: 100%;
            height: 64px;
            padding-top: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 112px;
            position: relative;
        }

        .rankingcoach-upsell-logo {
            max-width: 1200px;
            height: 100%;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
        }

        .rankingcoach-upsell-logo img {
            max-height: 40px;
            width: auto;
            object-fit: contain;
        }

        .rankingcoach-upsell-content {
            text-align: center;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 60px;
        }

    .rankingcoach-upsell-title,
    .rankingcoach-upsell-subtitle {
        font-family: 'Overpass', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
        font-size: 32px;
        font-weight: 600;
        line-height: 1.1;
        margin-top: 0;
        margin-bottom: 30px;
        color: #333;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .rankingcoach-upsell-description {
        font-size: 16px;
        line-height: 1.6;
        color: #666;
        margin-bottom: 10px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }				.rankingcoach-pricing-plans {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 50px;
            flex-wrap: wrap;
        }

        .rankingcoach-plan {
            background: #fff;
            border-radius: 26px;
            /*box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);*/
            flex: 1;
            min-width: 250px;
            max-width: 320px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease-in-out;
        }

        .rankingcoach-plan.recommended-plan {
            overflow: visible;
        }

        .plan-header {
            padding: 20px;
            /*background: #004ECC; !* Default color for Standard plan *!*/
            text-align: center;
            color: #fff;
        }

        /* Advanced plan specific header color */
        .rankingcoach-plan:nth-child(2) .plan-header {
            /*background: #0066FF;*/
        }

        .plan-header.greyed-out {
            background: #B7C2D6;
        }

        .recommended-plan .plan-header {
            /*background: #0066FF;*/
            border-top-left-radius: 26px;
            border-top-right-radius: 26px;
            z-index: 1;
        }

        .recommended-plan .plan-header,
        .recommended-plan:nth-child(n) .plan-header {
            /*background: #0066FF;*/
        }

        .recommended-plan .plan-header.greyed-out {
            background: #B7C2D6;
        }

        .current-plan .plan-header {
            background: #B7C2D6;
        }

        /* Reset blue color for Advanced plan when it's current */
        .current-plan:not(.recommended-plan) .plan-header {
            background: #B7C2D6;
        }

        /* Reset yellow/orange color for Pro plan when it's current */
        .recommended-plan.current-plan .plan-header {
            /*background: #FFC107;*/
        }

        .plan-name {
            font-family: 'Overpass', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #fff;
        }

        .plan-pricing {
            padding: 20px;
            text-align: center;
            display: none; /* Hide pricing section */
        }

        .plan-price {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .plan-price.greyed-out-text {
            color: #888;
        }

        .current-plan .plan-price {
            color: #B7C2D6;
        }

        .plan-period {
            font-size: 14px;
            color: #888;
            margin-left: 4px;
        }

        .plan-period.greyed-out-text {
            color: #B7C2D6;
        }

        .current-plan-label {
            /*background: #B7C2D6;*/
            color: #fff;
            padding: 10px;
            text-align: center;
            font-weight: 500;
        }

        .recommended-plan.current-plan .current-plan-label {
            /*background: #FFC107;*/
            color: #333;
        }

        .plan-cta {
            padding: 20px;
            text-align: center;
        }

        .upgrade-button {
            width: 100%;
            padding: 12px 24px !important;
            font-size: 15px !important;
            background-color: #0066FF !important;
            border-color: #0066FF !important;
            color: #fff !important;
        }

        .upgrade-button.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        .recommended-plan .upgrade-button {
            background-color: #5CC4E2 !important;
            border-color: #5CC4E2 !important;
            color: #0B2A63 !important;
        }

        .current-plan .upgrade-button {
            background-color: #B7C2D6 !important;
            border-color: #B7C2D6 !important;
            color: #fff !important;
        }

        .rankingcoach-plan:last-child .plan-header {
            /*background: #FFC107;*/
        }

        .rankingcoach-plan:last-child .upgrade-button {
            background-color: #0066FF !important
            border-color: #0066FF !important
            color: #333 !important;
        }

        .rankingcoach-plan:last-child.current-plan .plan-header {
            background: #B7C2D6;
        }

        .rankingcoach-plan:last-child.current-plan .upgrade-button {
            background-color: #B7C2D6 !important;
            border-color: #B7C2D6 !important;
            color: #fff !important;
        }

        .recommended-badge {
            position: absolute;
            top: 16px;
            right: 20px;
            background-color: #FFAA00;
            border-radius: 2px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.3;
            z-index: 2;
            white-space: nowrap;
        }

        .plan-features {
            padding: 20px;
            flex-grow: 1;
        }

        .plan-features ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            text-align: left;
        }

        .plan-features li {
            margin-bottom: 12px;
            padding-left: 28px;
            position: relative;
            font-size: 14px;
            line-height: 1.4;
        }

        .plan-features li strong {
            color: #333;
            font-weight: 600;
            display: inline-block;
            margin-left: -24px; /* Remove space for checkmark when strong */
        }

        .plan-features li strong + .checkmark {
            display: none; /* Hide checkmark for items with strong tag */
        }

        .plan-features .checkmark {
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
        }

        .plan-features li.greyed-out-text strong {
            color: #888;
        }

        .plan-terms {
            padding: 10px 20px;
            font-size: 12px;
            text-align: left;
            line-height: 1.4;
            color: #666;
        }

        .plan-terms input {
            margin-right: 5px;
        }

        .plan-terms a {
            color: #0073aa;
        }

        .terms-error-message {
            color: red;
            font-size: 12px;
            margin-top: 5px;
            text-align: left;
        }

        .wp-admin #wpcontent {
            padding-left: 0;
        }

        .wp-admin .notice {
            display: none;
        }

        /* Feature Cards Styling */
        .rankingcoach-feature-cards {
            max-width: 900px;
            margin: 80px auto 0;
            padding: 0 20px;
        }

        .feature-card {
            display: flex;
            align-items: flex-start;
            margin-bottom: 80px;
            justify-content: center;
            gap: 60px;
        }

        .feature-card.image-right {
            flex-direction: row-reverse;
        }

        .feature-content {
            width: 360px;
            margin: 0 40px;
            padding-top: 20px;
            text-align: left;
        }

        .feature-image {
            width: 360px;
            display: flex;
            justify-content: center;
        }

        .feature-image img {
            max-width: 100%;
            height: auto;
        }

        .feature-title {
            font-family: 'Overpass', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            font-size: 24px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 24px;
            color: #333;
            line-height: 1.3;
            text-align: left;
        }

        .feature-description {
            text-align: left;
            font-size: 16px;
            line-height: 1.6;
            color: #666;
            margin: 0;
        }

        /* Pro Section Styling */
    .rankingcoach-pro-section {
        text-align: center;
        margin-top: 80px;
        margin-bottom: 40px;
        padding: 0 40px;
    }
    .pro-title {
        font-family: 'Overpass', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
        font-size: 50px;
        font-weight: 700;
        line-height: 1.1;
        color: #333;
        margin-top: 90px;
        margin-bottom: 60px;
        padding: 0 20px;
    }
    .pro-highlight {
        color: #11C7E6;
    }				/* New Pro Feature Cards Container Styling */
        .rankingcoach-pro-feature-cards {
            max-width: 900px;
            margin: 40px auto 0;
            padding: 0 20px;
        }
        .rankingcoach-pro-feature-cards .feature-card {
            margin-bottom: 80px;
        }

        /* FAQ Section Styling */
        .rankingcoach-faq-section {
            max-width: 900px;
            margin: 80px auto 40px;
            padding: 0 20px;
            text-align: center;
        }

        .faq-section-title {
            font-family: 'Overpass', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-top: 0;
            margin-bottom: 40px;
        }

        .faq-item {
            margin-bottom: 30px;
        }

        .faq-question {
            font-family: 'Overpass', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-top: 0;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .faq-answer {
            font-size: 16px;
            line-height: 1.6;
            color: #666;
            margin: 0;
        }

        .rankingcoach-free-plan-message {
            display: inline-block;
            background-color: #F5F7F9;
            border: 1px solid #B6C1D3;
            color: #506586;
            padding: 12px 30px;
            border-radius: 5px;
            text-align: center;
            font-size: 18px;
            line-height: 1.4;
            margin: 20px auto 50px;
        }

        /*Mobile view*/
        @media (max-width: 768px) {
            .rankingcoach-upsell-header {
                margin-bottom: 60px;
            }

            .rankingcoach-upsell-title,
            .rankingcoach-upsell-subtitle {
                font-size: 26px;
                margin-bottom: 20px;
            }

            .rankingcoach-upsell-description {
                font-size: 15px;
                margin-bottom: 30px;
            }

            .pro-title {
                font-size: 36px;
                margin-bottom: 8px;
            }

            .pro-subtitle {
                font-size: 16px;
                padding-top: 8px;
                padding-bottom: 24px;
            }

            .faq-section-title {
                font-size: 28px;
                margin-bottom: 30px;
            }

            .faq-question {
                font-size: 18px;
            }

            .faq-answer {
                font-size: 15px;
            }

            .feature-card,
            .feature-card:nth-child(2) {
                flex-direction: column !important;
                text-align: center;
                gap: 15px;
            }

            .feature-content,
            .feature-image {
                flex: 1 1 auto;
                max-width: 100%;
            }

            .feature-title {
                margin-bottom: 16px;
            }
        }
    </style>
</div>
