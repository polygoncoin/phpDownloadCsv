# phpDownloadCsv
PHP Download CSV up to millions of records

To enable compression for downloading dynamically generated CSV files in NGINX if the browser supports compression, you can use the gzip_types directive in the NGINX configuration file. The gzip_types directive is used to specify which MIME types should be compressed.

Here's an example of how you can enable compression for downloading dynamically generated CSV files in NGINX:

<code>
http {

  gzip on;
  gzip_types text/plain text/csv;
}
</code>

In this example, we have enabled gzip compression and specified that text/plain and text/csv MIME types should be compressed. You can also use the text/* wildcard to include all text-based MIME types.

This configuration will automatically compress the content of dynamically generated CSV files if the browser supports compression, which can significantly reduce the size of the files and speed up their download time.
