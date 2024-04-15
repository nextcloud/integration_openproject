### 🧪 Running API tests

> **_NOTE:_**  
> Before running the API tests, the nextcloud instance needs to be ready, and also integration app needs to be enabled

To run the whole of the acceptance tests locally run the command below.
```shell
NEXTCLOUD_BASE_URL=http://<nextcloud_host> make api-test
```

In order to run only a specific scenario
```shell
NEXTCLOUD_BASE_URL=http://<nextcloud_host> \                                                                                                                                            
make api-test \
FEATURE_PATH=tests/acceptance/features/api/directUpload.feature:15
```
