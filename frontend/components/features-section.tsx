"use client"

import { Zap, Infinity, Check, Settings, Play, Cloud } from "lucide-react"

export default function FeaturesSection() {
  const features = [
    {
      Icon: Zap,
      title: "Easy to use and Fast Download",
      description:
        'Using this Yt1s Fast YouTube Downloader help to Download and Save MP4 and MP3 Easily. Just Copy and Paste the URL into search box and press "convert" button.',
      bgColor: "bg-blue-50",
      iconColor: "text-blue-600",
    },
    {
      Icon: Infinity,
      title: "Conversions without limit",
      description:
        "YT1s offers Unlimited Convert From YouTube and Download MP3 and MP4 without Length limit Free of cost.",
      bgColor: "bg-pink-50",
      iconColor: "text-pink-600",
    },
    {
      Icon: Check,
      title: "Totally Safe and Secure",
      description:
        "Device Security and personal data are of high priority to people when they download videos from a third-party website. YT1s secure to YouTube Downloader.",
      bgColor: "bg-green-50",
      iconColor: "text-green-600",
    },
  ]

  const secondRowFeatures = [
    {
      Icon: Settings,
      title: "Full Platforms Compatibility",
      description:
        "YT1s is fully compatible to Download YouTube videos and Audio on Devices like Windows, Mac or Linux, Android, and iPhone. Also Supports All Browsers Such As Chrome, Firefox, Safari, Microsoft Edge, etc.",
      bgColor: "bg-yellow-50",
      iconColor: "text-yellow-600",
    },
    {
      Icon: Play,
      title: "Support multiple formats",
      description:
        "YT1s allows conversion and downloading of YouTube Audio, Video, and other formats such as MP3, MP4, M4V, FLV, WEBM, 3GP, WMV, AVI, etc.",
      bgColor: "bg-purple-50",
      iconColor: "text-purple-600",
    },
    {
      Icon: Cloud,
      title: "Cloud integration",
      description: "We offer to upload and save converted files directly into Google Drive and Dropbox.",
      bgColor: "bg-cyan-50",
      iconColor: "text-cyan-600",
    },
  ]

  return (
    <section className="py-16 bg-background">
      <div className="max-w-6xl mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-4 text-foreground">Best Youtube Video Downloader</h2>

        {/* Description */}
        <div className="max-w-4xl mx-auto mb-12 text-center text-muted-foreground text-sm leading-relaxed">
          <p className="mb-4">
            <span className="font-semibold text-foreground">YT1s</span> is Free and Easy Youtube Downloader that Allows
            you to Convert and Download Youtube videos on Your Devices in multiple formats and best Quality. Using this
            YT1s Downloader You can Download Unlimited Video and Audio on Your Mobile and other Devices without Any
            registration and without spending Money. So You can Play MP3 and MP4 without an internet connection.
          </p>
          <p>
            Convert and download high-quality audio files in 64kbps, 128kbps, 192kbps, 256kbps, and 320kbps, and in
            different formats for free. YT1s works flawlessly on All Devices including computers, tablets, and mobile
            devices. YT1s also supports converting to many different formats such as MP3, MP4, M4V, FLV, WEBM, 3GP, WMV,
            AVI, etc without installing any special software or browser extensions.
          </p>
        </div>

        {/* Feature Cards - First Row */}
        <div className="grid md:grid-cols-3 gap-6 mb-12">
          {features.map((feature, index) => (
            <div key={index} className="rounded-lg overflow-hidden shadow-sm">
              <div className={`${feature.bgColor} h-40 flex items-center justify-center`}>
                <feature.Icon className={`${feature.iconColor} w-16 h-16`} />
              </div>
              <div className="p-6 bg-card">
                <h3 className="font-bold text-foreground mb-2">{feature.title}</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">{feature.description}</p>
              </div>
            </div>
          ))}
        </div>

        {/* Feature Cards - Second Row */}
        <div className="grid md:grid-cols-3 gap-6">
          {secondRowFeatures.map((feature, index) => (
            <div key={index} className="rounded-lg overflow-hidden shadow-sm">
              <div className={`${feature.bgColor} h-40 flex items-center justify-center`}>
                <feature.Icon className={`${feature.iconColor} w-16 h-16`} />
              </div>
              <div className="p-6 bg-card">
                <h3 className="font-bold text-foreground mb-2">{feature.title}</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">{feature.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
