<?php


/**
 * Content Lock Routes
 */
return [
    [
        'pattern' => '(:all)/lock',
        'method'  => 'GET',
        'action'  => function (string $path) {
			if ($lock = $this->parent($path)->lock()) {
                return [
					'lock' => $lock->toArray()
				];
			}

            return [
                'lock' => false
            ];
        }
    ],
	[
		'pattern' => '(:all)/lock',
		'method'  => 'PATCH',
		'action'  => function (string $path) {
			return $this->parent($path)->lock()?->create();
		}
	],
	[
		'pattern' => '(:all)/lock',
		'method'  => 'DELETE',
		'action'  => function (string $path) {
			return $this->parent($path)->lock()?->remove();
		}
	],
	[
		'pattern' => '(:all)/unlock',
		'method'  => 'PATCH',
		'action'  => function (string $path) {
			return  $this->parent($path)->lock()?->unlock();
		}
	],
	[
		'pattern' => '(:all)/unlock',
		'method'  => 'DELETE',
		'action'  => function (string $path) {
			return  $this->parent($path)->lock()?->resolve();
		}
	],
];
