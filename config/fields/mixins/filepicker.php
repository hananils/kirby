<?php

use Kirby\Panel\FilesPicker;

return [
	'methods' => [
		'filepicker' => function (array $params = []) {
			// fetch the parent model
			$params['model'] = $this->model();

			return (new FilesPicker($params))->toArray();
		}
	]
];
