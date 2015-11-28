angular.module('analizator', []).controller('analizatorController', ['$scope', '$http', function($scope, $http) {
    $scope.dummyText = 'dummy';
    $scope.products = [];
    $scope.users = [];
    $scope.sentences = [];
    $scope.opinionFlag = false;
    $scope.init = function () {
        var request = $http.post('/szovegbanyaszat/site.php', {});

        request.success(function (response) {
            console.log('success');
            console.log(response);
            $scope.users = response.users;
            $scope.products = response.products;
            $scope.sentences = response.senteces;
        });
        request.error(function (response) {
            console.log('error');
            console.log(response);
        });
    };
    $scope.confirmGetSentencesForProduct = function (product, user) {
        var id_product = '';
        var id_user = '';
        if (typeof(product) != "undefined") {
            id_product = product;
        }
        if (typeof(user) != "undefined") {
            id_user = user;
        }
        var data =  {'id_user':id_user, 'id_product':id_product, 'opinion_flag':$scope.opinionFlag};
        var request = $http.post('/szovegbanyaszat/site.php', data);

        request.success(function (response) {
            console.log('success sentences');
            console.log(response);
            $scope.users = response.users;
            $scope.products = response.products;
            $scope.sentences = response.sentences;
        });
        request.error(function (response) {
            console.log('error');
            console.log(response);
        });
    }
    $scope.getOpinionForSentence = function (sentence) {
        var sid = sentence.s_id;
        if (typeof(sentence.opinions) == "undefined") {
            sentence.opinions = [];
            var data =  {'requestType':'opinion', 'sid':sid};
            var request = $http.post('/szovegbanyaszat/site.php', data);

            request.success(function (response) {
                console.log('success opinion');
                console.log(response);
                sentence.opinions = response.opinions;
                console.log(sentence.opinions);
            });
            request.error(function (response) {
                console.log('error');
                console.log(response);
            });
        }
        
    };
}]);
