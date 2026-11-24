# PunktDe.Cloudflare.Stream

[![Latest Stable Version](https://poser.pugx.org/punktDe/cloudflare-stream/v/stable)](https://packagist.org/packages/punktDe/cloudflare-stream) [![Total Downloads](https://poser.pugx.org/punktDe/cloudflare-stream/downloads)](https://packagist.org/packages/punktDe/cloudflare-stream) [![License](https://poser.pugx.org/punktDe/cloudflare-stream/license)](https://packagist.org/packages/punktDe/cloudflare-stream)

When videos are uploaded to Neos, this package automatically uploads these videos to the [cloudflare stream service](https://www.cloudflare.com/de-de/products/cloudflare-stream/) and stores the provided DASH and HLS URIs, as well as the thumbnail URL for rendering them in the frontend.
An Eel helper is provided, to retrieve this meta data by a given video to use it in the video player of your choice.

## Installation

Install the package via composer

    $ composer require punktde/cloudflare-stream

## Configuration

Just configure you cloudflare credentials:

	PunktDe:
	  Cloudflare:
	    Stream:
	      cloudflare:
	        authentication:
	          accountIdentifier: '<AccountIdentifier>'
	          token: '<Bearer Token>'
	      transfer:
	        # Proxy to reach the cloudflare API
	        proxyUrl: ''

### Asynchronous upload

By default, videos are uploaded to cloudflare during the request which creates or replaces them. For large videos this
can take several minutes and may run into timeouts of proxies or the web server. Enable asynchronous processing to
upload them in a separate process instead:

	PunktDe:
	  Cloudflare:
	    Stream:
	      processing:
	        async: true
	        # Number of failed upload attempts after which a video is skipped
	        maximumAttempts: 3

Then run `./flow cloudflare:uploadPending` regularly, e.g. every minute by a cronjob. Until it has run, the
`Stream.getVideoMetaData()` eelHelper returns `null` for new videos, so make sure your rendering handles this case.
Make sure the command is not run concurrently, otherwise videos might be uploaded twice.

### Using a mock server for testing

The cloudflare API endpoint can be changed, e.g. to point to a local mock server in a development environment:

	PunktDe:
	  Cloudflare:
	    Stream:
	      cloudflare:
	        apiEndpoint: 'http://cloudflare-mock:8080/client/v4/'

## Get Stream meta data using the provided eelHelper


In your custome project code, add a node type with a video ptoperty to select or upload a local video asset. Access the cloudflare stream meta data using the `Stream.getVideoMetaData(videoObject)` eelHelper method.

**Example Fusion code**

	prototype(Vendor.Project:Content.Video) < prototype(Neos.Fusion:Component) {
	
	    video = ${q(node).property('video')}
	    streamMetaData = ${Stream.getVideoMetaData(this.video)}
	
	    @if.videoSelected = ${Type.isObject(this.video)}
	
	    renderer = afx`
	    <table>
	        <tr><td>CloudflareUid</td><td><b>{props.streamMetaData.cloudflareUid}</b></td></tr>
	        <tr><td>Thumbnail</td><td><img src={props.streamMetaData.thumbnailUri} /></td></tr>
	        <tr><td>HLS</td><td><a href={props.streamMetaData.hlsUri}>{props.streamMetaData.hlsUri}</a></td></tr>
	        <tr><td>DASH</td><td><a href={props.streamMetaData.dashUri}>{props.streamMetaData.dashUri}</a></td></tr>
	    </table>
	    `
	}

## Provided Commands

| Command                  | Description                               |
|--------------------------|-------------------------------------------|
| `cloudflare:listvideos ` | List all uploaded videos for that account |
| `cloudflare:deletevideo `| Delete a video from cloudflare            |
| `cloudflare:uploadAll`   | Upload all existing videos to cloudflare  |
| `cloudflare:uploadPending` | Upload all videos which are not yet uploaded to cloudflare (for asynchronous processing) |
