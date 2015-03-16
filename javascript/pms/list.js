controllers.controller('pmList', function ($scope, $cookies, $http, $location, DeletePM) {
	function getPMs(box, page) {
		if (isNaN(page)) 
			page = 1;
		$http.post(API_HOST + '/pms/list/', { box: box, page: page }).success(function (data) {
			data.pms.forEach(function (value, key) {
				data.pms[key].datestamp = convertTZ(value.datestamp, 'YYYY-MM-DD HH:mm:ss', 'MMMM D, YYYY h:mm a')
			});
			$scope.pms = data.pms;

			if (data.totalCount > PAGINATE_PER_PAGE) {
				$scope.pagination.numPages = Math.ceil(data.totalCount / PAGINATE_PER_PAGE);
				$scope.pagination.current = page;
				$scope.pagination.pages = new Array();
				for (count = $scope.pagination.numPages - 2 > 0?$scope.pagination.numPages - 2:1; count <= $scope.pagination.numPages + 2 && count <= $scope.pagination.numPages; count++) {
					$scope.pagination.pages.push(count);
				}
				$scope.showPagination = true;
				console.log($scope.pagination);
			}
		});
	}

	pathElements = getPathElements();

	$scope.pagination = {};
	if (typeof $location.search().page == 'undefined') 
		$scope.pagination.current = parseInt($location.search().page);
	else 
		$scope.pagination.current = 1;
	$scope.showPagination = false;
	$scope.box = pathElements[1] == 'outbox'?'Outbox':'Inbox';
	getPMs($scope.box.toLowerCase(), 1);

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

	$scope.changePage = function (page) {
		page = parseInt(page);
		if (page < 0 && page > $scope.pagination.numPages) 
			page = 1;
		getPMs($scope.box.toLowerCase(), page);

	}
});

$(function () {
	leftSpacing = $('#pms .hbDark .dlWing').css('borderRightWidth');
	$('#pmList, #newPM').css('margin', '0 ' + leftSpacing);
});