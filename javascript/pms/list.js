controllers.controller('pmList', function ($scope, $cookies, $http, DeletePM) {
	function getPMs(box) {
		$http.post('http://api.gamersplane.local/pms/list/', { box: box }).success(function (data) {
			data.pms.forEach(function (value, key) {
				data.pms[key].datestamp = convertTZ(value.datestamp, 'YYYY-MM-DD HH:mm:ss', 'MMMM D, YYYY h:mm a')
			});
			$scope.pms = data.pms;
		});
	}

	pathElements = getPathElements();

	$scope.box = pathElements[1] == 'outbox'?'Outbox':'Inbox';
	getPMs($scope.box.toLowerCase());

	$scope.switchBox = function ($event, box) {
		$event.preventDefault();
		newBox = box.capitalizeFirstLetter();
		if ($scope.box != newBox) {
			$scope.box = newBox;
			getPMs(box);
		}
	};

	$scope.delete = function (pmID) {
		DeletePM(pmID).success(function (data) {
			if (!isNaN(data.deleted)) 
				getPMs($scope.box.toLowerCase());
		});
	}
});

$(function () {
	leftSpacing = $('#pms .hbDark .dlWing').css('borderRightWidth');
	$('#pmList, #newPM').css('margin', '0 ' + leftSpacing);
});

function deleted(pmID) {
	$('#pm_' + pmID).remove();
	if ($('div.pm').length == 0) $('#noPMs').show();

	$.colorbox.close();
}