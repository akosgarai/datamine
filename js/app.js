angular.module('analizator', []).controller('analizatorController', ['$scope', '$http', function($scope, $http) {
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
    $scope.labelMap = function (index) {
        var mod = index % 6;
        var label = '';
        switch (mod) {
            case 0:
                label = 'label-default';
                break;
            case 1:
                label = 'label-primary';
                break;
            case 2:
                label = 'label-success';
                break;
            case 3:
                label = 'label-info';
                break;
            case 4:
                label = 'label-warning';
                break;
            case 5:
                label = 'label-danger';
                break;
        }
        return label;
    };
    $scope.activateAnalizator = function (sentence, forceFlag) {
        var sid = sentence.s_id;
        var productId = sentence.product_id;
        var text = sentence.text;
        var data = {'requestType':'analyze_sid','s_id':sid,'productId':productId,'text':text,'forceSkyttle':forceFlag};
        var request = $http.post('/szovegbanyaszat/site.php', data).then(
                function (response) {
                    console.log('analizator success callback');
                    sentence.skyttleData = response.data;
                    console.log(sentence.skyttleData);
                }, function (response){
                    console.log('analizator error callback');
                    console.log(response);
                }
            );
    }
}]);
