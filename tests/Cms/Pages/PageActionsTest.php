<?php

namespace Kirby\Cms;

use Kirby\Content\ContentTranslation;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use PHPUnit\Framework\TestCase;

class PageActionsTest extends TestCase
{
	protected $app;
	protected $tmp;

	public function setUp(): void
	{
		Dir::make($this->tmp = __DIR__ . '/tmp');

		$this->app = new App([
			'roots' => [
				'index' => $this->tmp
			],
		]);

		$this->app->impersonate('kirby');
	}

	public function tearDown(): void
	{
		Dir::remove($this->tmp);
	}

	public function site()
	{
		return $this->app->site();
	}

	public function slugProvider()
	{
		return [
			['test', 'test', true],
			['test', 'test', false],
			['modified-test', 'modified-test', true],
			['modified-test', 'modified-test', false],
			['mödified-tést', 'modified-test', true],
			['mödified-tést', 'modified-test', false]
		];
	}

	public function testChangeNum()
	{
		$phpunit = $this;

		$app = new App([
			'roots' => [
				'index' => '/dev/null'
			],
			'site' => [
				'children' => [
					[
						'slug' => 'test',
						'num'  => 1
					]
				]
			],
			'hooks' => [
				'page.changeNum:before' => function ($page, $num) use ($phpunit) {
					$phpunit->assertSame(2, $num);
				},
				'page.changeNum:after' => function ($newPage, $oldPage) use ($phpunit) {
					$phpunit->assertSame(1, $oldPage->num());
					$phpunit->assertSame(2, $newPage->num());
				}
			]
		]);

		$children = $app->site()->children();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$page = $children->find('test');

		$updatedPage = $page->changeNum(2);

		$this->assertNotSame($page, $updatedPage);
		$this->assertSame(2, $updatedPage->num());

		$this->assertSame($updatedPage, $children->find('test'));
		$this->assertSame($updatedPage, $childrenAndDrafts->find('test'));
	}

	public function testChangeNumWhenNumStaysTheSame()
	{
		$app = new App([
			'roots' => [
				'index' => '/dev/null'
			],
			'site' => [
				'children' => [
					[
						'slug' => 'test',
						'num'  => 1
					]
				]
			],
			'hooks' => [
				'page.changeNum:before' => function () {
					throw new \Exception('This should not be called');
				}
			]
		]);

		$children = $app->site()->children();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$page = $children->find('test');

		// the result page should stay the same
		$this->assertSame($page, $page->changeNum(1));

		$this->assertSame($page, $children->find('test'));
		$this->assertSame($page, $childrenAndDrafts->find('test'));
	}

	/**
	 * @dataProvider slugProvider
	 */
	public function testChangeSlug($input, $expected, $draft)
	{
		$site = $this->app->site();

		// pre-populate caches
		$site->children();
		$site->drafts();
		$site->childrenAndDrafts();

		if ($draft) {
			$page = Page::create([
				'slug' => 'test',
			]);

			$in      = 'drafts';
			$oldRoot = $this->tmp . '/content/_drafts/test';
			$newRoot = $this->tmp . '/content/_drafts/' . $expected;
		} else {
			$page = Page::create([
				'slug' => 'test',
				'num'  => 1
			]);

			$in      = 'children';
			$oldRoot = $this->tmp . '/content/1_test';
			$newRoot = $this->tmp . '/content/1_' . $expected;
		}

		$this->assertTrue($page->exists());
		$this->assertSame('test', $page->slug());

		$this->assertTrue($page->parentModel()->$in()->has('test'));
		$this->assertSame($oldRoot, $page->root());

		$modified = $page->changeSlug($input);

		$this->assertTrue($modified->exists());
		$this->assertSame($expected, $modified->slug());
		$this->assertSame($modified, $site->$in()->get($expected));
		$this->assertSame($modified, $site->childrenAndDrafts()->get($expected));
		$this->assertSame($newRoot, $modified->root());
	}

	/**
	 * @dataProvider slugProvider
	 */
	public function testChangeSlugMultiLang($input, $expected, $draft)
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');
		$site = $app->site();

		// pre-populate caches
		$site->children();
		$site->drafts();
		$site->childrenAndDrafts();

		if ($draft) {
			$page = Page::create([
				'slug' => 'test',
			]);

			$in   = 'drafts';
			$root = $this->tmp . '/content/_drafts/test';
		} else {
			$page = Page::create([
				'slug' => 'test',
				'num'  => 1
			]);

			$in   = 'children';
			$root = $this->tmp . '/content/1_test';
		}

		$page = $page->update(['slug' => 'test-de'], 'de');

		$this->assertTrue($page->exists());
		$this->assertSame('test', $page->slug());
		$this->assertSame('test-de', $page->slug('de'));

		$this->assertTrue($page->parentModel()->$in()->has('test'));
		$this->assertSame($root, $page->root());

		$modified = $page->changeSlug($input, 'de');

		$this->assertTrue($modified->exists());
		$this->assertSame('test', $modified->slug());
		$this->assertSame($expected, $modified->slug('de'));
		$this->assertSame($modified, $site->$in()->get('test'));
		$this->assertSame($modified, $site->childrenAndDrafts()->get('test'));
		$this->assertSame($root, $modified->root());
	}

	public function testChangeTemplate()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'blueprints' => [
				'pages/video' => [
					'title'  => 'Video',
					'options' => [
						'template' => [
							'article'
						]
					],
					'fields' => [
						'caption' => [
							'type' => 'text'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				],
				'pages/article' => [
					'title'  => 'Article',
					'fields' => [
						'caption' => [
							'type' => 'info'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				]
			],
			'site' => [
				'drafts' => [
					[
						'slug'     => 'test',
						'template' => 'video',
						'content'  => [
							'title'   => 'Test',
							'caption' => 'Caption',
							'text'    => 'Text'
						]
					]
				]
			],
			'hooks' => [
				'page.changeTemplate:before' => function (Page $page, $template) use ($phpunit, &$calls) {
					$phpunit->assertSame('video', $page->intendedTemplate()->name());
					$phpunit->assertSame('article', $template);
					$calls++;
				},
				'page.changeTemplate:after' => function (Page $newPage, Page $oldPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('article', $newPage->intendedTemplate()->name());
					$phpunit->assertSame('video', $oldPage->intendedTemplate()->name());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$page = $drafts->find('test');

		$this->assertSame('video', $page->intendedTemplate()->name());
		$this->assertSame('Caption', $page->caption()->value());
		$this->assertSame('Text', $page->text()->value());

		$modified = $page->changeTemplate('article');

		$this->assertSame('article', $modified->intendedTemplate()->name());
		$this->assertNull($modified->caption()->value());
		$this->assertSame('Text', $modified->text()->value());
		$this->assertSame(2, $calls);

		$this->assertSame($modified, $drafts->find('test'));
		$this->assertSame($modified, $childrenAndDrafts->find('test'));
	}

	public function testChangeTemplateMultilang()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'blueprints' => [
				'pages/video' => [
					'title'  => 'Video',
					'options' => [
						'template' => [
							'article'
						]
					],
					'fields' => [
						'caption' => [
							'type' => 'text'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				],
				'pages/article' => [
					'title'  => 'Article',
					'fields' => [
						'caption' => [
							'type' => 'radio'
						],
						'text' => [
							'type' => 'textarea'
						]
					]
				]
			],
			'hooks' => [
				'page.changeTemplate:before' => function (Page $page, $template) use ($phpunit, &$calls) {
					$phpunit->assertSame('video', $page->intendedTemplate()->name());
					$phpunit->assertSame('article', $template);
					$calls++;
				},
				'page.changeTemplate:after' => function (Page $newPage, Page $oldPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('article', $newPage->intendedTemplate()->name());
					$phpunit->assertSame('video', $oldPage->intendedTemplate()->name());
					$calls++;
				}
			],
			'languages' => [
				[
					'code' => 'en',
					'name' => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch',
				],
				[
					'code' => 'fr',
					'name' => 'Français',
				]
			],
			'site' => [
				'children' => [
					[
						'slug'     => 'test',
						'template' => 'video',
						'translations' => [
							[
								'code' => 'en',
								'content' => [
									'title'   => 'Test',
									'caption' => 'Caption',
									'text'    => 'Text'
								]
							],
							[
								'code' => 'de',
								'content' => [
									'title'   => 'Prüfen',
									'caption' => 'Untertitel',
									'text'    => 'Täxt'
								]
							],
						]
					]
				]
			],
		]);

		$app->impersonate('kirby');
		$page = $app->page('test');

		$this->assertSame('video', $page->intendedTemplate()->name());
		$this->assertSame('Caption', $page->caption()->value());
		$this->assertSame('Text', $page->text()->value());
		$this->assertSame('Untertitel', $page->content('de')->get('caption')->value());
		$this->assertSame('Täxt', $page->content('de')->get('text')->value());

		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$modified = $page->changeTemplate('article');

		$this->assertSame('article', $modified->intendedTemplate()->name());
		$this->assertSame(2, $calls);

		$this->assertFileExists($modified->storage()->contentFile('published', 'en'));
		$this->assertFileExists($modified->storage()->contentFile('published', 'de'));
		$this->assertFileDoesNotExist($modified->storage()->contentFile('published', 'fr'));
		$this->assertNull($modified->caption()->value());
		$this->assertSame('Text', $modified->text()->value());
		$this->assertNull($modified->content('de')->get('caption')->value());
		$this->assertSame('Täxt', $modified->content('de')->get('text')->value());
	}

	public function testChangeTitle()
	{
		$page = $this->app->site()->createChild([
			'slug' => 'test'
		]);

		$this->assertSame('test', $page->title()->value());

		$drafts = $this->app->site()->drafts();
		$childrenAndDrafts = $this->app->site()->childrenAndDrafts();

		$modified = $page->changeTitle($title = 'Modified Title');

		$this->assertSame($title, $modified->title()->value());

		$this->assertSame($modified, $drafts->find('test'));
		$this->assertSame($modified, $childrenAndDrafts->find('test'));
	}

	public function testMove()
	{
		$this->app = $this->app->clone([
			'blueprints' => [
				'pages/parent' => [
					'sections' => [
						'subpages' => [
							'type'     => 'pages',
							'template' => 'child'
						]
					]
				]
			]
		]);

		$this->app->impersonate('kirby');

		$parentA = $this->app->site()->createChild([
			'slug'     => 'parent-a',
			'template' => 'parent'
		]);

		$parentB = $this->app->site()->createChild([
			'slug'     => 'parent-b',
			'template' => 'parent'
		]);

		$child = $parentA->createChild([
			'slug'     => 'child',
			'template' => 'child'
		]);

		$moved = $child->move($parentB);

		$this->assertTrue($moved->parent()->is($parentB));
	}

	public function testPurge()
	{
		$page = new Page([
			'slug' => 'test'
		]);

		$page->children();
		$page->drafts();
		$page->childrenAndDrafts();

		$this->assertNotNull($page->children);
		$this->assertNotNull($page->drafts);
		$this->assertNotNull($page->childrenAndDrafts);

		$this->assertSame($page, $page->purge());

		$this->assertNull($page->children);
		$this->assertNull($page->drafts);
		$this->assertNull($page->childrenAndDrafts);
	}

	public function testSave()
	{
		$page = new Page([
			'slug' => 'test'
		]);

		$this->assertFalse($page->exists());
		$page->save();
		$this->assertTrue($page->exists());
	}

	public function testUpdate()
	{
		$page = $this->app->site()->createChild([
			'slug' => 'test'
		]);

		$this->assertNull($page->headline()->value());

		$drafts = $this->app->site()->drafts();
		$childrenAndDrafts = $this->app->site()->childrenAndDrafts();

		$oldStatus = $page->status();
		$modified  = $page->update(['headline' => 'Test']);

		$this->assertSame('Test', $modified->headline()->value());

		// assert that the page status didn't change with the update
		$this->assertSame($oldStatus, $modified->status());

		$this->assertSame($modified, $drafts->find('test'));
		$this->assertSame($modified, $childrenAndDrafts->find('test'));
	}

	public function testUpdateHooks()
	{
		$phpunit = $this;
		$calls = 0;

		$app = $this->app->clone([
			'hooks' => [
				'page.update:before' => function (Page $page, $values, $strings) use (&$calls, $phpunit) {
					$calls++;
					$phpunit->assertSame('foo', $page->category()->value());
					$phpunit->assertSame('foo', $page->siblings()->pluck('category')[0]->toString());
					$phpunit->assertSame('bar', $page->siblings()->pluck('category')[1]->toString());
					$phpunit->assertSame('foo', $page->parent()->children()->pluck('category')[0]->toString());
					$phpunit->assertSame('bar', $page->parent()->children()->pluck('category')[1]->toString());
				},
				'page.update:after' => function (Page $newPage, Page $oldPage) use (&$calls, $phpunit) {
					$calls++;
					$phpunit->assertSame('homer', $newPage->category()->value());
					$phpunit->assertSame('homer', $newPage->siblings()->pluck('category')[0]->toString());
					$phpunit->assertSame('bar', $newPage->siblings()->pluck('category')[1]->toString());
					$phpunit->assertSame('homer', $newPage->parent()->children()->pluck('category')[0]->toString());
					$phpunit->assertSame('bar', $newPage->parent()->children()->pluck('category')[1]->toString());
				}
			],
			'site' => [
				'children' => [
					[
						'slug' => 'test',
						'children' => [
							[
								'slug' => 'a',
								'content' => [
									'category' => 'foo'
								]
							],
							[
								'slug' => 'b',
								'content' => [
									'category' => 'bar'
								]
							]
						]
					]
				]
			]
		]);

		$app->impersonate('kirby');
		$app->page('test/a')->update(['category' => 'homer']);

		$this->assertSame(2, $calls);
	}

	public function languageProvider()
	{
		return [
			[null],
			['en'],
			['de']
		];
	}

	/**
	 * @dataProvider languageProvider
	 */
	public function testUpdateMultilang($languageCode)
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');

		if ($languageCode !== null) {
			$app->setCurrentLanguage($languageCode);
		}

		$page = $this->app->site()->createChild([
			'slug' => 'test'
		]);

		$this->assertNull($page->headline()->value());

		$drafts = $this->app->site()->drafts();
		$childrenAndDrafts = $this->app->site()->childrenAndDrafts();

		$modified = $page->update(['headline' => 'Test'], $languageCode);

		// check the modified response
		$this->assertSame('Test', $modified->headline()->value());

		// also check in a freshly found page object
		$this->assertSame('Test', $this->app->page('test')->headline()->value());

		$this->assertSame($modified, $drafts->find('test'));
		$this->assertSame($modified, $childrenAndDrafts->find('test'));
	}

	public function testUpdateMergeMultilang()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');

		$page = $this->app->site()->createChild([
			'slug' => 'test'
		]);

		$drafts = $this->app->site()->drafts();
		$childrenAndDrafts = $this->app->site()->childrenAndDrafts();

		// add some content in both languages
		$page = $page->update([
			'a' => 'A (en)',
			'b' => 'B (en)'
		], 'en');

		$page = $page->update([
			'a' => 'A (de)',
			'b' => 'B (de)'
		], 'de');

		$this->assertSame('A (en)', $page->content('en')->a()->value());
		$this->assertSame('B (en)', $page->content('en')->b()->value());
		$this->assertSame('A (de)', $page->content('de')->a()->value());
		$this->assertSame('B (de)', $page->content('de')->b()->value());

		$this->assertSame($page, $drafts->find('test'));
		$this->assertSame($page, $childrenAndDrafts->find('test'));

		// update a single field in the primary language
		$page = $page->update([
			'b' => 'B modified (en)'
		], 'en');

		$this->assertSame('A (en)', $page->content('en')->a()->value());
		$this->assertSame('B modified (en)', $page->content('en')->b()->value());

		$this->assertSame($page, $drafts->find('test'));
		$this->assertSame($page, $childrenAndDrafts->find('test'));

		// update a single field in the secondary language
		$page = $page->update([
			'b' => 'B modified (de)'
		], 'de');

		$this->assertSame('A (de)', $page->content('de')->a()->value());
		$this->assertSame('B modified (de)', $page->content('de')->b()->value());

		$this->assertSame($page, $drafts->find('test'));
		$this->assertSame($page, $childrenAndDrafts->find('test'));
	}

	public function testChangeStatusDraftHooks()
	{
		$phpunit = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.changeStatus:before' => function (Page $page, $status, $position) use ($phpunit) {
					$phpunit->assertSame('draft', $status);
					$phpunit->assertNull($position);
				},
				'page.changeStatus:after' => function (Page $newPage, Page $oldPage) use ($phpunit) {
					$phpunit->assertSame('listed', $oldPage->status());
					$phpunit->assertSame('draft', $newPage->status());
				}
			],
			'site' => [
				'children' => [
					['slug' => 'test', 'num' => 1]
				]
			]
		]);

		$app->impersonate('kirby');

		$page = $app->page('test');

		$children = $app->site()->children();
		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$this->assertSame($page, $children->find('test'));

		$newPage = $page->changeStatus('draft');

		$this->assertSame($newPage, $drafts->find('test'));
		$this->assertNull($children->find('test'));
		$this->assertSame($newPage, $childrenAndDrafts->find('test'));
	}

	public function testChangeStatusListedHooks()
	{
		$phpunit = $this;
		$before  = 0;
		$after   = 0;

		$app = $this->app->clone([
			'hooks' => [
				'page.changeStatus:before' => function (Page $page, $status, $position) use (&$before, $phpunit) {
					$phpunit->assertSame('listed', $status);
					$phpunit->assertSame($before + 1, $position);
					$before++;
				},
				'page.changeStatus:after' => function (Page $newPage, Page $oldPage) use (&$after, $phpunit) {
					$phpunit->assertSame('draft', $oldPage->status());
					$phpunit->assertSame('listed', $newPage->status());
					$after++;
				}
			]
		]);

		$app->impersonate('kirby');

		$pageA = Page::create(['slug' => 'test-a', 'num' => null]);
		$pageB = Page::create(['slug' => 'test-b', 'num' => null]);

		$children = $app->site()->children();
		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$this->assertSame($pageA, $drafts->find('test-a'));

		$newPageA = $pageA->changeStatus('listed');
		$newPageB = $pageB->changeStatus('listed');

		$this->assertSame(2, $before);
		$this->assertSame(2, $after);

		$this->assertSame($newPageA, $children->find('test-a'));
		$this->assertNull($drafts->find('test-a'));
		$this->assertSame($newPageA, $childrenAndDrafts->find('test-a'));
	}

	public function testChangeStatusUnlistedHooks()
	{
		$phpunit = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.changeStatus:before' => function (Page $page, $status, $position) use ($phpunit) {
					$phpunit->assertSame('unlisted', $status);
					$phpunit->assertNull($position);
				},
				'page.changeStatus:after' => function (Page $newPage, Page $oldPage) use ($phpunit) {
					$phpunit->assertSame('draft', $oldPage->status());
					$phpunit->assertSame('unlisted', $newPage->status());
				}
			]
		]);

		$app->impersonate('kirby');

		$page = Page::create(['slug' => 'test']);

		$children = $app->site()->children();
		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$this->assertSame($page, $drafts->find('test'));

		$newPage = $page->changeStatus('unlisted');

		$this->assertSame($newPage, $children->find('test'));
		$this->assertNull($drafts->find('test'));
		$this->assertSame($newPage, $childrenAndDrafts->find('test'));
	}

	public function testDuplicate()
	{
		$this->app->impersonate('kirby');

		$page = $this->app->site()->createChild([
			'slug' => 'test',
		]);
		$page->lock()->create();
		$this->assertFileExists($this->app->locks()->file($page));

		// check UUID exists
		$oldUuid = $page->content()->get('uuid')->value();
		$this->assertIsString($oldUuid);

		$drafts = $this->app->site()->drafts();
		$childrenAndDrafts = $this->app->site()->childrenAndDrafts();

		$copy = $page->duplicate('test-copy');

		$this->assertFileDoesNotExist($this->tmp . $copy->root() . '/.lock');

		$this->assertSame($page, $drafts->find('test'));
		$this->assertSame($page, $childrenAndDrafts->find('test'));

		// check UUID got updated
		$newUuid = $copy->content()->get('uuid')->value();
		$this->assertIsString($newUuid);
		$this->assertNotSame($oldUuid, $newUuid);
	}

	public function testDuplicateMultiLang()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code' => 'en',
					'name' => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch',
				]
			]
		]);

		$app->impersonate('kirby');

		$page = $app->site()->createChild([
			'slug' => 'test',
		]);

		new ContentTranslation([
			'parent' => $page,
			'code'   => 'en',
		]);
		$this->assertFileExists($page->storage()->contentFile('changes', 'en'));

		$drafts = $app->site()->drafts();
		$childrenAndDrafts = $app->site()->childrenAndDrafts();

		$copy = $page->duplicate('test-copy');

		$this->assertFileExists($copy->storage()->contentFile('changes', 'en'));
		$this->assertFileDoesNotExist($copy->storage()->contentFile('changes', 'de'));

		$this->assertSame($page, $drafts->find('test'));
		$this->assertSame($page, $childrenAndDrafts->find('test'));
	}

	public function testDuplicateMultiLangSlug()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code' => 'en',
					'name' => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch',
				]
			]
		]);

		$app->impersonate('kirby');

		$page = $app->site()->createChild([
			'slug'    => 'test',
		]);

		$page = $page->update([
			'slug'  => 'test-de'
		], 'de');

		$this->assertFileExists($page->storage()->contentFile('changes', 'en'));
		$this->assertFileExists($page->storage()->contentFile('changes', 'de'));
		$this->assertSame('test', $page->slug());
		$this->assertSame('test-de', $page->slug('de'));

		$copy = $page->duplicate('test-copy');

		$this->assertSame('test-copy', $copy->slug());
		$this->assertSame('test-copy', $copy->slug('de'));
	}

	public function testDuplicateFiles()
	{
		$this->app->impersonate('kirby');

		$page = $this->app->site()->createChild([
			'slug' => 'test',
			'files' => [
				['filename' => 'foo.jpg'],
			]
		]);

		F::write($this->tmp . '/content/_drafts/test/foo.jpg', '');

		$copy = $page->duplicate('test-copy', ['files' => true]);

		$origFile = $page->file('foo.jpg');
		$copyFile = $copy->file('foo.jpg');

		$this->assertNotSame($origFile->uuid()->id(), $copyFile->uuid()->id());
	}

	public function testDuplicateFilesMultiLang()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code' => 'en',
					'name' => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch',
				]
			]
		]);

		$app->impersonate('kirby');

		$app->site()->createChild([
			'slug' => 'test',
			'files' => [
				['filename' => 'foo.jpg'],
			]
		]);

		F::write($this->tmp . '/content/_drafts/test/foo.jpg', '');

		$page = $app->call('de/test');
		$page->duplicate('test-copy', ['files' => true]);
		$copy = $app->call('de/test-copy');

		$origFile = $page->file('foo.jpg');
		$copyFile = $copy->file('foo.jpg');

		$this->assertNotSame($origFile->uuid()->id(), $copyFile->uuid()->id());

		// check if the files collection has been properly updated
		$this->assertSame($copy->files()->find('foo.jpg')->uuid()->id(), $copyFile->uuid()->id());
	}

	public function testDuplicateChildren()
	{
		$app = $this->app->clone();
		$app->impersonate('kirby');

		$page = $app->site()->createChild(['slug' => 'test']);
		$page->createChild(['slug' => 'foo', 'template' => 'default']);

		$page = $app->page('test');
		$copy = $page->duplicate('test-copy', ['children' => true]);

		$this->assertNotSame($page->uuid()->id(), $copy->uuid()->id());
		$this->assertNotSame($app->page('test/foo')->uuid()->id(), $app->page('test-copy/foo')->uuid()->id());
	}

	public function testDuplicateChildrenMultiLang()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code' => 'en',
					'name' => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch',
				]
			]
		]);

		$app->impersonate('kirby');

		$page = $app->site()->createChild(['slug' => 'test']);
		$page->createChild(['slug' => 'foo', 'template' => 'default']);

		new ContentTranslation([
			'parent' => $page,
			'code'   => 'en'
		]);

		$copy = $page->duplicate('test-copy', ['children' => true]);

		$this->assertFileExists($copy->storage()->contentFile('changes', 'en'));
		$this->assertFileDoesNotExist($copy->storage()->contentFile('changes', 'de'));


		$this->assertNotSame($page->uuid()->id(), $copy->uuid()->id());
		$this->assertNotSame($app->page('test/foo')->uuid()->id(), $app->page('test-copy/foo')->uuid()->id());
	}

	public function testDuplicateChildrenFiles()
	{
		$app = $this->app->clone();
		$app->impersonate('kirby');

		$page = $app->site()->createChild(['slug' => 'test']);
		$page->createChild([
			'slug' => 'foo',
			'template' => 'default',
			'files' => [
				['filename' => 'foo.jpg'],
			]
		]);
		F::write($this->tmp . '/content/_drafts/test/_drafts/foo/foo.jpg', '');

		$page = $app->page('test');
		$copy = $page->duplicate('test-copy', [
			'children' => true,
			'files' => true
		]);

		$this->assertNotSame($page->uuid()->id(), $copy->uuid()->id());

		$origFile = $app->page('test/foo')->file('foo.jpg');
		$copyFile = $app->page('test-copy/foo')->file('foo.jpg');

		$this->assertNotSame($origFile->uuid()->id(), $copyFile->uuid()->id());
	}

	public function testChangeSlugHooks()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.changeSlug:before' => function (Page $page, $slug, $languageCode) use ($phpunit, &$calls) {
					$phpunit->assertSame('test', $page->slug());
					$phpunit->assertSame('new-test', $slug);
					$phpunit->assertNull($languageCode);
					$calls++;
				},
				'page.changeSlug:after' => function (Page $newPage, Page $oldPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('new-test', $newPage->slug());
					$phpunit->assertSame('test', $oldPage->slug());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		$page = new Page([
			'slug' => 'test'
		]);

		$page->changeSlug('new-test');

		$this->assertSame(2, $calls);
	}

	public function testChangeTitleHooks()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.changeTitle:before' => function (Page $page, $title, $languageCode) use ($phpunit, &$calls) {
					$phpunit->assertSame('test', $page->title()->value);
					$phpunit->assertSame('New Title', $title);
					$phpunit->assertNull($languageCode);
					$calls++;
				},
				'page.changeTitle:after' => function (Page $newPage, Page $oldPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('New Title', $newPage->title()->value());
					$phpunit->assertSame('test', $oldPage->title()->value());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		$page = new Page([
			'slug' => 'test'
		]);

		$page->changeTitle('New Title');

		$this->assertSame(2, $calls);
	}

	public function testCreateHooks()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.create:before' => function (Page $page, $input) use ($phpunit, &$calls) {
					$phpunit->assertInstanceOf(Page::class, $page);
					$phpunit->assertSame('test', $input['slug']);
					$phpunit->assertSame('default', $input['model']);
					$phpunit->assertSame('default', $input['template']);
					$phpunit->assertTrue($input['isDraft']);
					$phpunit->assertArrayHasKey('uuid', $input['content']);
					$calls++;
				},
				'page.create:after' => function (Page $page) use ($phpunit, &$calls) {
					$phpunit->assertInstanceOf(Page::class, $page);
					$phpunit->assertSame('test', $page->slug());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		Page::create([
			'slug' => 'test',
		]);

		$this->assertSame(2, $calls);
	}

	public function testDeleteHooks()
	{
		$calls = 0;
		$phpunit  = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.delete:before' => function (Page $page, $force) use ($phpunit, &$calls) {
					$phpunit->assertInstanceOf(Page::class, $page);
					$phpunit->assertFalse($force);
					$phpunit->assertFileExists($page->root());
					$calls++;
				},
				'page.delete:after' => function ($status, Page $page) use ($phpunit, &$calls) {
					$phpunit->assertTrue($status);
					$phpunit->assertInstanceOf(Page::class, $page);
					$phpunit->assertFileDoesNotExist($page->root());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		$page = Page::create([
			'slug' => 'test'
		]);

		$page->delete();

		$this->assertSame(2, $calls);
	}

	public function testDuplicateHooks()
	{
		$calls = 0;
		$phpunit = $this;

		$app = $this->app->clone([
			'hooks' => [
				'page.duplicate:before' => function (Page $originalPage, $input, $options) use ($phpunit, &$calls) {
					$phpunit->assertSame('test', $originalPage->slug());
					$phpunit->assertSame('test-copy', $input);
					$phpunit->assertSame([], $options);
					$calls++;
				},
				'page.duplicate:after' => function (Page $duplicatePage, Page $originalPage) use ($phpunit, &$calls) {
					$phpunit->assertSame('test-copy', $duplicatePage->slug());
					$phpunit->assertSame('test', $originalPage->slug());
					$calls++;
				}
			]
		]);

		$app->impersonate('kirby');

		$page = Page::create([
			'slug' => 'test'
		]);

		$page->duplicate();

		$this->assertSame(2, $calls);
	}

	public function testCreateDefaultLanguage()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');

		$value = [
			'title'    => 'Test page',
			'headline' => 'A headline',
			'text'     => 'Any text'
		];

		$page = Page::create([
			'slug'      => 'test',
			'content'   => $value,
			'blueprint' => [
				'title'  => 'Default',
				'fields' => [
					'headline' => ['type' => 'text'],
					'text'     => ['type' => 'textarea']
				]
			],
		]);

		$value['uuid'] = $page->content()->get('uuid')->value();

		$this->assertSame($value, $page->content('en')->toArray());
		$this->assertSame($value, $page->content('de')->toArray());
	}

	public function testCreateSecondaryLanguage()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');
		$app->setCurrentLanguage('de');

		$value = [
			'title'    => 'Test page',
			'headline' => 'A headline',
			'text'     => 'Any text'
		];

		$page = Page::create([
			'slug'      => 'test',
			'content'   => $value,
			'blueprint' => [
				'title'  => 'Default',
				'fields' => [
					'headline' => ['type' => 'text'],
					'text'     => ['type' => 'textarea']
				]
			]
		]);

		$value['uuid'] = $page->content()->get('uuid')->value();

		$this->assertSame('de', $app->language()->code());
		$this->assertSame($value, $page->content('en')->toArray());
		$this->assertSame($value, $page->content('de')->toArray());
	}

	public function testCreateSecondaryLanguageUntranslatable()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');
		$app->setCurrentLanguage('de');

		$value = [
			'title'    => 'Test page',
			'headline' => 'A headline',
			'text'     => 'Any text'
		];

		$page = Page::create([
			'slug'      => 'test',
			'content'   => $value,
			'blueprint' => [
				'title'  => 'Default',
				'fields' => [
					'headline' => [
						'type'      => 'text',
						'translate' => false
					],
					'text'     => ['type' => 'textarea']
				]
			]
		]);

		$value['uuid'] = $page->content()->get('uuid')->value();

		$this->assertSame('de', $app->language()->code());
		$this->assertSame($value, $page->content('en')->toArray());
		$this->assertSame($value, $page->content('de')->toArray());
	}

	public function testCreateSecondaryLanguageDefaultValues()
	{
		$app = $this->app->clone([
			'languages' => [
				[
					'code'    => 'en',
					'name'    => 'English',
					'default' => true
				],
				[
					'code' => 'de',
					'name' => 'Deutsch'
				]
			]
		]);

		$app->impersonate('kirby');
		$app->setCurrentLanguage('de');

		$page = Page::create([
			'slug'       => 'test',
			'content'    => ['title' => 'Test page'],
			'blueprint'  => [
				'title'  => 'test',
				'fields' => [
					'headline' => [
						'type'      => 'text',
						'translate' => false,
						'default'   => 'A headline'
					],
					'text'     => [
						'type'    => 'textarea',
						'default' => 'Any text'
					]
				]
			]
		]);

		$expected = [
			'title'    => 'Test page',
			'uuid'     =>  $page->content()->get('uuid')->value(),
			'headline' => 'A headline',
			'text'     => 'Any text',
		];

		$this->assertSame('de', $app->language()->code());
		$this->assertSame($expected, $page->content('en')->toArray());
		$this->assertSame($expected, $page->content('de')->toArray());
	}

	public function testUnpublish()
	{
		$page = Page::create([
			'slug' => 'test',
			'draft' => false
		]);

		Page::create([
			'slug' => 'child-a',
			'draft' => false,
			'num' => 1,
			'parent' => $page
		]);

		Page::create([
			'slug' => 'child-b',
			'draft' => false,
			'num' => 2,
			'parent' => $page
		]);

		Page::create([
			'slug' => 'child-c',
			'draft' => false,
			'parent' => $page
		]);

		Page::create([
			'slug' => 'child-d',
			'draft' => true,
			'parent' => $page
		]);

		$listed = $page->children()->listed();
		$unlisted = $page->children()->unlisted();
		$drafts = $page->drafts();

		$this->assertCount(2, $listed);
		foreach ($listed as $child) {
			$this->assertSame('listed', $child->status());
		}

		$this->assertCount(1, $unlisted);
		foreach ($unlisted as $child) {
			$this->assertSame('unlisted', $child->status());
		}

		$this->assertCount(1, $drafts);
		foreach ($drafts as $child) {
			$this->assertSame('draft', $child->status());
		}

		// unpublish all
		foreach ($page->children() as $child) {
			$child->unpublish();
		}

		// make sure that not cached children
		$clone = $page->clone();

		$this->assertCount(0, $clone->children()->listed());
		$this->assertCount(0, $clone->children()->unlisted());
		$this->assertCount(4, $clone->drafts());
	}
}
