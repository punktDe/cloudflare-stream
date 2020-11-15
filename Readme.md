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
| `cloudflare:uploadAll`   | Upload all existign videos to cloudflare  |
