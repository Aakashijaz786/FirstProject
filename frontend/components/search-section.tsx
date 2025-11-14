"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"

export default function SearchSection() {
  const [url, setUrl] = useState("")

  const handleConvert = () => {
    if (url.trim()) {
      // Handle conversion logic here
      console.log("Converting:", url)
    }
  }

  return (
    <section className="bg-background py-12">
      <div className="max-w-4xl mx-auto px-4">
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold text-foreground mb-2">YT1S - Youtube Video Downloader</h1>
          <p className="text-muted-foreground">Convert and Download Youtube Video Online Free</p>
        </div>

        {/* Search Input */}
        <div className="flex gap-2 mb-4">
          <input
            type="text"
            placeholder="Search or paste Youtube link here"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            onKeyPress={(e) => e.key === "Enter" && handleConvert()}
            className="flex-1 px-4 py-3 border border-border rounded-lg bg-card text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-red-600"
          />
          <Button
            onClick={handleConvert}
            className="bg-red-600 hover:bg-red-700 text-white px-8 py-3 rounded-lg font-medium"
          >
            Convert
          </Button>
        </div>
      </div>
    </section>
  )
}
