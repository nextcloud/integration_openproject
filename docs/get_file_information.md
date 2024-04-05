## Get file information
We have two endpoints `fileinfo` and `filesinfo` from which we can get the information of a single or multiple files.

### Requirements
This endpoint has a soft dependency on Nextcloud's [Activity](https://github.com/nextcloud/activity) app. In the absence of the Activity app the response fields like `modifier_name` , `modifier_id` will be returned as null. So, it is encouraged to have Activity app installed and enabled.

1. **Get information of single file**: 
Send the `GET` request to `fileinfo` endpoint with `FILE_ID` of the file to retrieve information.

	```bash
	curl -H "Accept: application/json" -H "OCS-APIRequest: true" -u USER:PASSWD http://<nextcloud_host>/ocs/v1.php/apps/integration_openproject/fileinfo/<FILE_ID>
	
	```
	Upon success the response from the above curl request will be
	```json
	{
	  "ocs": {
		"meta": {
		  "status": "ok",
		  "statuscode": 100,
		  "message": "OK",
		  "totalitems": <totalitems>,
		  "itemsperpage": <itemsperpage>
		},
		"data": {
		  "status": "OK",
		  "statuscode": 200,
		  "id": <FILE_ID>,
		  "name": <file_name>,
		  "mtime": <mtime>,
		  "ctime": <ctime>,
		  "mimetype": <mimetype>,
		  "size": <size>,
		  "owner_name": <owner_name>,
		  "owner_id": <owner_id>,
		  "trashed": <boolean>,
		  "modifier_name": <modifier_name>,
		  "modifier_id": <modifier_id>,
		  "dav_permissions": <dav_permissions>,
		  "path": <path>
		}
	  }
	}
	```
2. **Get information of multiple files**:
Send the `POST` request to `filesinfo` endpoint with data `fileIds` of the files to retrieve information.

    ```bash
    curl -H "Accept: application/json" -H "Content-Type:application/json" -H "OCS-APIRequest: true" -u USER:PASSWD http://<nextcloud_host>/ocs/v1.php/apps/integration_openproject/filesinfo -X POST -d '{"fileIds":[<FILE_ID1>, <FILE_ID2> ,...]}'
	
    ```
    Upon success the response from the above curl request will be
    ```json
    {
        "ocs": {
            "meta": {
                "status": "ok",
                "statuscode": 100,
                "message": "OK",
                "totalitems": <totalitems>,
                "itemsperpage": <itemsperpage>
            },
            "data": {
                <FILE_ID1>: {
                    "status": "OK",
                    "statuscode": 200,
                    "id": <FILE_ID1>,
                    "name": <file_name>,
                    "mtime": <mtime>,
                    "ctime": <ctime>,
                    "mimetype": <mimetype>,
                    "size": <size>,
                    "owner_name": <owner_name>,
                    "owner_id": <owner_id>,
                    "trashed": <boolean>,
                    "modifier_name": <modifier_name>,
                    "modifier_id": <modifier_id>,
                    "dav_permissions": <dav_permissions>,
                    "path": <path>
                },
                <FILE_ID2>: {
                    "status": "OK",
                    "statuscode": 200,
                    "id": <FILE_ID1>,
                    "name": <file_name>,
                    "mtime": <mtime>,
                    "ctime": <ctime>,
                    "mimetype": <mimetype>,
                    "size": <size>,
                    "owner_name": <owner_name>,
                    "owner_id": <owner_id>,
                    "trashed": <boolean>,
                    "modifier_name": <modifier_name>,
                    "modifier_id": <modifier_id>,
                    "dav_permissions": <dav_permissions>,
                    "path": <path>
                },
   		   ...
            }
        }
    }
    ```
