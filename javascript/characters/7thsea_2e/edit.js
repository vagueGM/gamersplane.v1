controllers.controller('editCharacter_7thsea_2e', ['$scope', '$http', '$sce', '$timeout', '$filter', 'CurrentUser', 'CharactersService', 'Range', function ($scope, $http, $sce, $timeout, $filter, CurrentUser, CharactersService, Range) {
	CurrentUser.load().then(function () {
		blanks = {
			'reputations': '',
			'stories': { 'name': '', 'goal': '', 'reward': '', 'steps': '' },
			'backgrounds': { 'name': '', 'quirk': '' },
			'advantages': { 'name': '', 'description': '' }
		};
		$scope.range = Range.get;
		$scope.character = {};
		$scope.loadChar().then(function () {
			console.log($scope.character);
			$scope.$watch(function () { return $scope.character.nation; }, function () {
				if (typeof $scope.character.nation != 'string') {
					return;
				}
				var match = $filter('filter')($scope.bookData.nations, { 'nation_clean': $scope.character.nation.toLowerCase() }, true);
				if (match.length) {
					$scope.bookMatch.nation = match[0];
				} else {
					$scope.bookMatch.nation = null;
				}
			});

			$scope.$watch(
				function () { return $scope.character.arcana.virtue.arcana; },
				function () {
					showArcanaSetFromBook('virtue');
				}
			);
			$scope.$watch(
				function () { return $scope.character.arcana.hubris.arcana; },
				function () {
					showArcanaSetFromBook('hubris');
				}
			);

			function showArcanaSetFromBook(type) {
				if (typeof $scope.character.arcana[type].arcana != 'string') {
					return;
				}
				match = $filter('filter')($scope.bookData.arcana, { 'type': type, arcana_clean: $scope.character.arcana[type].arcana.toLowerCase() }, true);
				if (match.length) {
					$scope.bookMatch.arcana[type] = match[0];
				} else {
					$scope.bookMatch.arcana[type] = null;
				}
			}

			$scope.setArcanaFromBook = function (setFrom, type) {
				$scope.character.arcana[type].name = $scope.bookMatch.arcana[type].name_clean;
				$scope.character.arcana[type].description = $scope.bookMatch.arcana[type].desc;
			};
		});

		$scope.bookData = {
			'nation': null,
			'arcana': null
		};
		$scope.combobox = {
			'nation': [],
			'arcana': [],
			'virtue': [],
			'hubris': []
		};
		$scope.bookMatch = {
			'nation': null,
			'arcana': {
				'virtue': null,
				'hubris': null
			}
		};
		CharactersService.getBookData('7thsea_2e').then(function (data) {
			$scope.bookData = data;
			$scope.bookData.nations.forEach(function (nation, index) {
				$scope.combobox.nation.push({
					'value': nation.nation_clean,
					'display': nation.nation
				});
			});
			$scope.bookData.arcana.forEach(function (arcana, index) {
				if (arcana.type == 'virtue') {
					$scope.combobox.arcana.push({
						'value': arcana.arcana_clean,
						'display': arcana.arcana
					});
				}
				$scope.combobox[arcana.type].push({
					'value': arcana.name_clean,
					'display': arcana.name
				});
			});
		});

		$scope.addReputation = function () {
			$scope.character.reputations.push('');
		};
		$scope.setDeathSpiral = function (value) {
			value = parseInt(value);
			if (value > $scope.character.deathSpiral)
				for (count = 1; count <= Math.floor(value / 5); count++)
					$scope.character.dramaticWounds[count] = true;
			$scope.character.deathSpiral = parseInt(value);
		};
	});
}]);
