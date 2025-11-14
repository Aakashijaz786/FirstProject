"use client"

import { Music, FileVideo, Smartphone, Film, Volume2 } from "lucide-react"

export default function FormatsSection() {
  const formats = [
    { name: "MP4", Icon: FileVideo },
    { name: "MP3", Icon: Music },
    { name: "3GP", Icon: Smartphone },
    { name: "WEBM", Icon: Film },
    { name: "M4A", Icon: Volume2 },
  ]

  return (
    <section className="py-16 bg-card border-t border-border">
      <div className="max-w-6xl mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-12 text-foreground">
          Download Youtube videos Free using Yt1s
        </h2>

        {/* Format Badges */}
        <div className="flex flex-wrap justify-center gap-4">
          {formats.map((format, index) => (
            <div
              key={index}
              className="bg-primary text-primary-foreground px-6 py-4 rounded-lg font-bold text-center min-w-24 hover:opacity-90 transition-opacity"
            >
              <div className="mb-2 flex justify-center">
                <format.Icon className="w-6 h-6" />
              </div>
              <div>{format.name}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
