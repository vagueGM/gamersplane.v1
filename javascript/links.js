controllers.controller('links', ['$scope', '$http', '$sce', '$filter', 'Links', function ($scope, $http, $sce, $filter, links) {
	$scope.$emit('pageLoading');
	$scope.links = [];
	Links.get().then(function (data) {
		$scope.links = data.links;
		$scope.$emit('pageLoading');
	});
	$scope.categories = links.categories;
	$scope.filter = [];

	$scope.maxHeight = {
		'partners': 0,
		'rpgan': 0,
		'affiliates': 0,
		'links': 0
	}
}]);