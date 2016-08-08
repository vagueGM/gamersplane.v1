controllers.controller('editCharacter_13thage', ['$scope', '$timeout', 'CurrentUser', 'CharactersService', function ($scope, $timeout, CurrentUser, CharactersService) {
	CurrentUser.load().then(function () {
		$scope.labels = {
			'stats': [
				{ 'key': 'str', 'value': 'Strength' },
				{ 'key': 'dex', 'value': 'Dexterity' },
				{ 'key': 'con', 'value': 'Constitution' },
				{ 'key': 'int', 'value': 'Intelligence' },
				{ 'key': 'wis', 'value': 'Wisdom' },
				{ 'key': 'cha', 'value': 'Charisma' }
			]
		};
		blanks = {
			'skills': { 'name': '', 'rating': 1, 'type': 'a' },
			'qualities': { 'name': '', 'notes': '', 'type': 'p' },
			'contacts': { 'name': '', 'loyalty': 0, 'connection': 0, 'notes': '' },
			'weapons.ranged': { 'name': '', 'damage': '', 'accuracy': 0, 'ap': 0, 'mode': '', 'rc': '', 'ammo': '', 'notes': '' },
			'weapons.melee': { 'name': '', 'reach': 0, 'damage': '', 'accuracy': 0, 'ap': 0, 'notes': '' },
			'armor': { 'name': '', 'rating': 0, 'notes': '' },
			'cyberdeck.programs': { 'name': '', 'notes': '' },
			'augmentations': { 'name': '', 'rating': 0, 'essence': 0, 'notes': '' },
			'sprcf': { 'name': '', 'tt': '', 'range': '', 'duration': '', 'drain': 0, 'notes': '' },
			'powers': { 'name': '', 'rating': 0, 'notes': '' },
			'gear': { 'name': '', 'rating': 0, 'notes': '' }
		};
		$scope.loadChar();
		$scope.searchSkills = function (search) {
			return ACSearch.cil('skill', search, 'shadowrun5').then(function (items) {
				for (key in items) {
					systemItem = items[key].systemItem;
					items[key] = {
						'value': items[key].itemID,
						'display': items[key].name,
						'class': []
					}
					if (!systemItem)
						items[key].class.push('nonSystemItem');
				}
				return items;
			});
		};
		$scope.searchQualities = function (search) {
			return ACSearch.cil('quality', search, 'shadowrun5', true);
		};
		$scope.searchPrograms = function (search) {
			return ACSearch.cil('program', search, 'shadowrun5', true);
		};
		$scope.searchAugmentations = function (search) {
			return ACSearch.cil('augmentation', search, 'shadowrun5', true);
		};
		$scope.searchSPRCF = function (search) {
			return ACSearch.cil('sprcf', search, 'shadowrun5', true);
		};
		$scope.searchPowers = function (search) {
			return ACSearch.cil('powers', search, 'shadowrun5', true);
		};
//		$scope.save = function () {
//			$parent.save();
//		};
	});
}]);

function trigger_levelUpdate(oldLevel) {
	$('.addHL').each(function () {
		$(this).text(showSign(parseInt($(this).text()) - Math.floor(oldLevel / 2) + Math.floor(level / 2)));
	});
}

function updateStats() {
	$.each(['ac', 'pd', 'md'], function (key, value) {
		$statRow = $('#' + value + 'Row');
		total = parseInt($statRow.find('.saveStat').text()) + level;
		$statRow.find('input').each(function () {
			total += parseInt($(this).val());
		});
		$statRow.find('.total').text(total);
	});
}

$(function() {
	itemizationFunctions['backgrounds'] = {
		newItem: function ($newItem) {
			$newItem.appendTo('#backgroundList').find('input').placeholder().focus();
		},
		init: function ($list) {
			$list.find('input').placeholder();
		}
	}
	setupItemized($('#backgrounds'));
	$('#backgrounds').on('click', '.notesLink', function(e) {
		e.preventDefault();

		$(this).siblings('textarea').slideToggle();
	});
	$('.name').placeholder().autocomplete('/characters/ajax/autocomplete/', { type: 'background', characterID: characterID, system: system });

	itemizationFunctions['abilitiesTalents'] = {
		newItem: function ($newItem) {
			$newItem.appendTo('#abilitiesTalentsList').find('input').placeholder().focus();
		},
		init: function ($list) {
			$list.find('input').placeholder();
		}
	}
	setupItemized($('#abilitiesTalents'));
	$('#abilitiesTalents').on('click', '.notesLink', function(e) {
		e.preventDefault();

		$(this).siblings('textarea').slideToggle();
	});
	$('.name').placeholder().autocomplete('/characters/ajax/autocomplete/', { type: 'abilitiesTalent', characterID: characterID, system: system });

	itemizationFunctions['powers'] = {
		newItem: function ($newItem) {
			$newItem.appendTo('#powerList').find('input').placeholder().focus();
		},
		init: function ($list) {
			$list.find('input').placeholder();
		}
	}
	setupItemized($('#powers'));
	$('#powers').on('click', '.notesLink', function(e) {
		e.preventDefault();

		$(this).siblings('textarea').slideToggle();
	});
	$('.name').placeholder().autocomplete('/characters/ajax/autocomplete/', { type: 'power', characterID: characterID, system: system });

	itemizationFunctions['attacks'] = {
		newItem: function ($newItem) {
			$newItem.appendTo('#attackList').find('input').placeholder().focus();
		},
		init: function ($list) {
			$list.find('input').placeholder();
		}
	}
	setupItemized($('#attacks'));
	$('#attacks').on('click', '.notesLink', function(e) {
		e.preventDefault();

		$(this).siblings('textarea').slideToggle();
	});

	$('.stat').blur(function () {
		$.each({
			'ac': ['dex', 'con', 'wis'],
			'pd': ['str', 'dex', 'con'],
			'md': ['int', 'wis', 'cha']
		}, function (index, stats){
			if (statBonus[stats[1]] > statBonus[stats[0]]) {
				hold = stats[0];
				stats[0] = stats[1];
				stats[1] = hold;
			}
			if (statBonus[stats[2]] > statBonus[stats[1]]) {
				hold = stats[1];
				stats[1] = stats[2];
				stats[2] = hold;
			}
			if (statBonus[stats[1]] > statBonus[stats[0]]) {
				hold = stats[0];
				stats[0] = stats[1];
				stats[1] = hold;
			}
			$('#' + index + 'Stat').text(showSign(statBonus[stats[1]]));
		});
		updateStats();
	});

	$('#saves').on('blur', 'input', updateStats);

	$basicAttacks = $('#basicAttacks');
	basicAttacks = {
		'melee': {
			'stat': $basicAttacks.find('#ba_melee select').val(),
			'misc': $basicAttacks.find('#ba_melee input').val()
		},
		'ranged': {
			'stat': $basicAttacks.find('#ba_ranged select').val(),
			'misc': $basicAttacks.find('#ba_ranged input').val()
		}
	};
	$basicAttacks.on('change', 'input', function () {
		$row = $(this).closest('.tr');
		$row.children('.total').text(showSign(parseInt($row.children('.total').text()) - basicAttacks[$row.data('type')]['misc'] + parseInt($(this).val())));
		basicAttacks[$row.data('type')]['misc'] = parseInt($(this).val());
	}).on('change', 'select', function() {
		$row = $(this).closest('.tr');
		$row.children('.total').text(showSign(parseInt($row.children('.total').text()) - statBonus[basicAttacks[$row.data('type')]['stat']] + statBonus[$(this).val()])).removeClass('addStat_' + basicAttacks[$row.data('type')]['stat']).addClass('addStat_' + $(this).val());
		basicAttacks[$row.data('type')]['stat'] = $(this).val();
	});
});
