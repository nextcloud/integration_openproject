## Direct upload
There's an end-point `direct-upload` available which can be used for direct-upload. There's two steps to direct upload. First we need to get the `token`. Then use the token in the direct upload request.

1. **Preparation for direct upload**:
   Send the `POST` request to `direct-upload-token` end-point with data `folder_id` of the destination folder.
   ```console
   curl -u USER:PASSWD http://<nextcloud_host>/index.php/apps/integration_openproject/direct-upload-token -d '{"folder_id":<folder_id>}' -H'Content-Type: application/json'
   ```
   The response from the above curl request will be
   ```json
   {
       "token": "<token>",
       "expires_on": <some_timestamp>
   }
   ```
   > Note: The token is one-time only.

2. **Direct upload**:
   Send a multipart form data POST request to the `direct-upload` end-point to upload the file with `token` acquired from the preparation endpoint. The API takes an optional parameter `overwrite`.
   1. **Direct upload without overwrite parameter**:
      If the `overwrite` parameter is not set, a file will only be uploaded if no file exists with that name, otherwise a conflict error is thrown.
      ```console
      curl -X POST 'http://<nextcloud_host>/index.php/apps/integration_openproject/direct-upload/<token>' \
      --form 'file=@"<path-of-file>"' -H'Content-Type: multipart/form-data'
      ```

      The response from the above curl request will be
      ```json
      {
          "file_name": "<file_name>",
          "file_id": <file_id>
      }  
      ```
   
   2. **Direct upload with overwrite parameter**:
      The overwrite parameter can be either set to `true` or `false`.
      1. **overwrite set to false**: 
         If the parameter is set to `false` and a file with the name already exists, a new file will be uploaded with the existing name and a number suffix.
          ```console
         curl -X POST 'http://<nextcloud_host>/index.php/apps/integration_openproject/direct-upload/<token>' \
         --form 'file=@"<path-of-file>"' --form 'overwrite="false"' -H'Content-Type: multipart/form-data' 
           ```

          The response from the above curl request will be
          ```json
          {
              "file_name": "<file_name>(some-number).ext",
              "file_id": <file_id>
          }
          ```

      2. **overwrite set to true**: 
         If the parameter is set to `true` and a file with the name already exists, the existing file will be overwritten.
          ```console
         curl -X POST 'http://<nextcloud_host>/index.php/apps/integration_openproject/direct-upload/<token>' \
         --form 'file=@"<path-of-file>"' --form 'overwrite="true"' -H'Content-Type: multipart/form-data' 
           ```

          The response from the above curl request will be
          ```json
          {
              "file_name": "<file_name>",
              "file_id": <file_id>
          }
          ```
          Suppose we have a file with name `file.txt` in the server, and we send a request to direct-upload api with file `file.txt` and overwrite set to `true` then the response will be:
		  ```json
		  {
			  "file_name": "file (2).txt",
			  "file_id": 123
		  }
		  ```
		 > Note: The file id in this case is the id of the original file. Only the content is overwritten.
